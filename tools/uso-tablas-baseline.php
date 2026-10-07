<?php

/** Análisis estático y dependencias reales del esquema restaurado temporalmente. */
function auditarUsoTablas(PDO $pdo, string $root): array
{
    $relations = $pdo->query("SELECT n.nspname||'.'||c.relname AS name,c.relkind AS kind FROM pg_class c JOIN pg_namespace n ON n.oid=c.relnamespace WHERE n.nspname IN ('dbo','adq','public') AND c.relkind IN ('r','p','v') ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $report = [];
    foreach ($relations as $relation) {
        $report[$relation['name']] = ['kind' => $relation['kind'], 'evidence' => [], 'dependencies' => [], 'decision' => 'retirar'];
    }
    $sources = [];
    foreach (['backend/app', 'backend/routes', 'backend/resources', 'backend/database/seeders', 'backend/config', 'backend/bootstrap'] as $directory) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$directory, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $path = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            $code = '';
            foreach (token_get_all(file_get_contents($file->getPathname())) as $token) {
                $code .= is_array($token) ? (in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true) ? str_repeat("\n", substr_count($token[1], "\n")) : $token[1]) : $token;
            }
            $sources[$path] = $code;
        }
    }
    ksort($sources);
    $add = function (string $name, string $file, int $line, string $mode) use (&$report): void {
        if (! isset($report[$name])) {
            throw new RuntimeException('Referencia a una relación ausente en el esquema: '.$name.' en '.$file);
        }
        $report[$name]['evidence'][] = ['file' => $file, 'line' => $line, 'mode' => $mode];
    };
    $models = [];
    foreach ($sources as $file => $code) {
        if (! str_starts_with($file, 'backend/app/Models/')) {
            continue;
        }
        preg_match('/class\s+(\w+)/', $code, $class);
        preg_match('/namespace\s+([^;]+);/', $code, $namespace);
        preg_match('/protected\s+\$table\s*=\s*[\'"]([^\'"]+)[\'"]/', $code, $table);
        if ($class) {
            // User utiliza la convención Eloquent; el resto declara su tabla explícita.
            $name = $table[1] ?? ($class[1] === 'User' ? 'users' : null);
            if ($name) {
                $models[($namespace[1] ?? '').'\\'.$class[1]] = ['table' => str_contains($name, '.') ? $name : 'dbo.'.$name, 'file' => $file, 'class' => $class[1]];
            }
        }
    }
    foreach ($sources as $file => $code) {
        // Estos utilitarios manejan metadatos, no constituyen uso de negocio.
        if (preg_match('~/(BaselineRrhhService|AdoptarBaselineRrhh|LimpiarProduccion)\.php$~', $file)) {
            continue;
        }
        // Las cadenas calificadas también cubren SQL crudo, pivotes y mapas dinámicos.
        foreach ($report as $name => $row) {
            $pattern = '/(?<![\w.])'.preg_quote($name, '/').'(?!\w)/';
            if (preg_match_all($pattern, $code, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as [$match, $offset]) {
                    $line = substr_count(substr($code, 0, $offset), "\n") + 1;
                    $end = strpos($code, ';', $offset);
                    $fragment = substr($code, $offset, ($end === false ? $offset + 600 : $end) - $offset);
                    $mode = preg_match('/->(?:insert|insertGetId|upsert|update|updateOrInsert|delete|truncate|increment|decrement)\s*\(/i', $fragment) ? 'escritura' : 'lectura/referencia';
                    if (preg_match('/->(?:join|leftJoin|rightJoin|from)\(\s*[\'"]$/i', substr($code, max(0, $offset - 32), min(32, $offset)))) {
                        $mode = 'lectura_join';
                    }
                    if (str_starts_with($file, 'backend/app/Models/')) {
                        $mode = 'declaracion_modelo';
                    }
                    $add($name, $file, $line, $mode);
                }
            }
        }
        preg_match('/namespace\s+([^;]+);/', $code, $namespace);
        $imports = [];
        preg_match_all('/^use\s+(App\\\\Models\\\\[\w\\\\]+)(?:\s+as\s+(\w+))?\s*;/m', $code, $uses, PREG_SET_ORDER);
        foreach ($uses as $use) {
            $parts = explode('\\', $use[1]);
            $imports[$use[1]] = $use[2] ?? end($parts);
        }
        foreach ($models as $qualifiedClass => $model) {
            if ($file === $model['file']) {
                continue;
            }
            $class = $imports[$qualifiedClass] ?? ((($namespace[1] ?? '').'\\'.$model['class'] === $qualifiedClass) ? $model['class'] : null);
            if ($class === null) {
                // Permitir referencias con namespace completo sin un import.
                $class = $qualifiedClass;
            }
            $pattern = '/\b'.preg_quote($class, '/').'\s*::\s*(\w+)/';
            if (preg_match_all($pattern, $code, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $index => [$match, $offset]) {
                    $method = strtolower($matches[1][$index][0]);
                    $end = strpos($code, ';', $offset);
                    $fragment = substr($code, $offset, ($end === false ? $offset + 600 : $end) - $offset);
                    $write = in_array($method, ['create', 'insert', 'update', 'destroy', 'delete', 'firstorcreate', 'updateorcreate', 'upsert'], true) || preg_match('/->(?:insert|update|delete|increment|decrement)\s*\(/i', $fragment);
                    $mode = $write ? 'escritura_modelo' : ($method === 'class' ? 'relacion_modelo' : 'lectura_modelo');
                    $add($model['table'], $file, substr_count(substr($code, 0, $offset), "\n") + 1, $mode);
                }
            }
            // Escrituras sobre una instancia obtenida mediante el modelo.
            if (preg_match_all('/\$(\w+)\s*=\s*'.preg_quote($class, '/').'\s*::[^;]+;/', $code, $assignments, PREG_SET_ORDER)) {
                foreach ($assignments as $assignment) {
                    if (preg_match_all('/\$'.preg_quote($assignment[1], '/').'\s*->\s*(?:save|update|delete|increment|decrement)\s*\(/', $code, $writes, PREG_OFFSET_CAPTURE)) {
                        foreach ($writes[0] as [$match, $offset]) {
                            $add($model['table'], $file, substr_count(substr($code, 0, $offset), "\n") + 1, 'escritura_instancia_modelo');
                        }
                    }
                }
            }
        }
    }
    $baseTables = file($root.'/docs/tablas-datos-base-original.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($baseTables as $index => $table) {
        $add($table, 'docs/tablas-datos-base-original.txt', $index + 1, 'export_original');
    }
    // Uso indirecto de Laravel/Sanctum según los defaults de configuración y search_path dbo.
    foreach (['cache' => 'cache.php', 'cache_locks' => 'cache.php', 'sessions' => 'session.php', 'jobs' => 'queue.php', 'job_batches' => 'queue.php', 'failed_jobs' => 'queue.php', 'password_reset_tokens' => 'auth.php', 'users' => 'auth.php', 'migrations' => 'database.php'] as $table => $config) {
        $add('dbo.'.$table, 'backend/config/'.$config, 1, 'lectura/escritura_framework');
    }
    $foreign = $pdo->query("SELECT n.nspname||'.'||c.relname AS child,pn.nspname||'.'||p.relname AS parent FROM pg_constraint f JOIN pg_class c ON c.oid=f.conrelid JOIN pg_namespace n ON n.oid=c.relnamespace JOIN pg_class p ON p.oid=f.confrelid JOIN pg_namespace pn ON pn.oid=p.relnamespace WHERE f.contype='f'")->fetchAll(PDO::FETCH_ASSOC);
    $views = $pdo->query("SELECT DISTINCT vn.nspname||'.'||v.relname AS child,tn.nspname||'.'||t.relname AS parent FROM pg_rewrite r JOIN pg_class v ON v.oid=r.ev_class JOIN pg_namespace vn ON vn.oid=v.relnamespace JOIN pg_depend d ON d.classid='pg_rewrite'::regclass AND d.objid=r.oid JOIN pg_class t ON t.oid=d.refobjid JOIN pg_namespace tn ON tn.oid=t.relnamespace WHERE d.refclassid='pg_class'::regclass AND v.relkind='v' AND v.oid<>t.oid")->fetchAll(PDO::FETCH_ASSOC);
    foreach (array_merge($foreign, $views) as $edge) {
        if (isset($report[$edge['child']], $report[$edge['parent']])) {
            $report[$edge['child']]['dependencies'][] = $edge['parent'];
        }
    }
    // Cuerpos de funciones de triggers pueden incluir SQL sin dependencia pg_depend.
    $triggers = $pdo->query("SELECT n.nspname||'.'||c.relname AS child,pg_get_functiondef(p.oid) AS body FROM pg_trigger t JOIN pg_class c ON c.oid=t.tgrelid JOIN pg_namespace n ON n.oid=c.relnamespace JOIN pg_proc p ON p.oid=t.tgfoid WHERE NOT t.tgisinternal")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($triggers as $trigger) {
        if (! isset($report[$trigger['child']])) {
            continue;
        }
        foreach (array_keys($report) as $name) {
            if ($name !== $trigger['child'] && preg_match('/(?<![\w.])'.preg_quote($name, '/').'(?!\w)/', $trigger['body'])) {
                $report[$trigger['child']]['dependencies'][] = $name;
            }
        }
    }
    foreach ($report as $name => &$row) {
        $row['dependencies'] = array_values(array_unique($row['dependencies']));
        // Una declaración de modelo sin consumidores no demuestra lectura/escritura.
        $use = array_filter($row['evidence'], fn ($evidence) => ! in_array($evidence['mode'], ['declaracion_modelo', 'export_original'], true));
        if ($use && $name !== 'public.migrations') {
            $row['decision'] = 'conservar_uso';
        }
    }
    unset($row);
    do {
        $changed = false;
        foreach ($report as $name => $row) {
            if ($row['decision'] === 'retirar') {
                continue;
            }
            foreach ($row['dependencies'] as $parent) {
                if ($report[$parent]['decision'] === 'retirar') {
                    $report[$parent]['decision'] = 'conservar_dependencia';
                    $report[$parent]['required_by'] = $name;
                    $changed = true;
                }
            }
        }
    } while ($changed);
    // Laravel genera su historial, no se incluye ningún historial original en el SQL.
    foreach (['dbo.migrations', 'public.migrations'] as $name) {
        $report[$name]['decision'] = 'historial_laravel';
    }

    return $report;
}

/** Inventario legible en Excel, una fila por tabla/vista, sin datos personales. */
function exportarUsoTablasCsv(array $report, string $path): void
{
    $csv = fopen($path, 'wb');
    fputcsv($csv, ['tabla', 'tipo', 'decision', 'modulos', 'modos', 'dependencias', 'requerida_por', 'referencias'], ',', '"', '');
    foreach ($report as $name => $row) {
        $modes = $modules = $references = [];
        foreach ($row['evidence'] as $evidence) {
            $file = $evidence['file'];
            $module = match (true) {
                str_starts_with($file, 'docs/') => 'Export original',
                str_contains($file, '/config/'), str_contains($file, '/Providers/') => 'Framework/autenticación',
                str_contains($file, '/Adquisiciones/'), str_contains($file, '/Adq/'), str_contains($file, 'ArticulosSeeder') => 'Adquisiciones',
                str_contains($file, '/Transporte/'), str_contains($file, 'TransporteController'), str_contains($file, '/trans_') => 'Transportes',
                str_contains($file, '/Tecnologia/') => 'Tecnología',
                preg_match('~/(?:Controllers|Models)/Com[A-Z]~', $file) === 1, str_contains($file, '/com_') => 'Comisiones',
                str_contains($file, '/Nomina/'), str_contains($file, '/Nomina'), str_contains($file, '/RolPago'), str_contains($file, '/HorasExtras'), str_contains($file, '/He'), str_contains($file, '/Decimo'), str_contains($file, '/FondosReserva') => 'Nómina/horas extras',
                str_contains($file, 'Vacacion'), str_contains($file, 'Planificacion'), str_contains($file, 'LiquidacionVac') => 'Vacaciones',
                str_contains($file, 'Permiso') => 'Permisos',
                str_contains($file, '/Services/'), str_contains($file, '/Asistencia'), str_contains($file, '/Zkteco'), str_contains($file, '/ProcesarCuadre') => 'Servicios/asistencia/autenticación',
                default => 'Talento Humano/administración/reportes',
            };
            $modules[] = $module;
            $modes[] = $evidence['mode'];
            $references[] = $file.':'.$evidence['line'].' ['.$evidence['mode'].']';
        }
        fputcsv($csv, [$name, $row['kind'], $row['decision'], implode(' | ', array_unique($modules)), implode(' | ', array_unique($modes)), implode(' | ', $row['dependencies']), $row['required_by'] ?? '', implode(' | ', $references)], ',', '"', '');
    }
    fclose($csv);
}
