<?php

// Genera fuentes de instalación desde los dos exports, exclusivamente en PostgreSQL local.
// Requiere un usuario capaz de crear/eliminar una BD temporal y cambiar replication_role.
use App\Services\BaselineRrhhService;
use Illuminate\Contracts\Console\Kernel;
use Symfony\Component\Process\Process;

$root = dirname(__DIR__);
require $root.'/backend/vendor/autoload.php';
require __DIR__.'/uso-tablas-baseline.php';
$app = require $root.'/backend/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$config = config('database.connections.pgsql');
if (app()->environment() !== 'local' || $config['host'] !== '127.0.0.1' || ! empty($config['url'])) {
    throw new RuntimeException('Solo se permite la conexión local explícita, sin DB_URL.');
}
$dataPath = $argv[1] ?? '';
$schemaPath = $argv[2] ?? $root.'/backend/storage/app/baseline-source/estructura_BDD_RRHH.sql';
if (! is_file($dataPath) || ! is_file($schemaPath)) {
    throw new RuntimeException('Se requieren los exports de estructura y datos.');
}
$tables = file($root.'/docs/tablas-datos-base.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$originalTables = file($root.'/docs/tablas-datos-base-original.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$dataSql = file_get_contents($dataPath);
preg_match_all('/^-- Data for Name: (\w+); Type: TABLE DATA; Schema: (dbo|adq);/m', $dataSql, $blocks, PREG_SET_ORDER);
$actual = array_map(fn ($block) => $block[2].'.'.$block[1], $blocks);
sort($tables);
sort($originalTables);
sort($actual);
if ($actual !== $tables && $actual !== $originalTables) {
    throw new RuntimeException('Las tablas exportadas no coinciden con la lista completa.');
}
$pdo = new PDO('pgsql:host=127.0.0.1;port='.$config['port'].';dbname='.$config['database'], $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$temporary = 'rrhh_baseline_build_'.bin2hex(random_bytes(6));
$pdo->exec('CREATE DATABASE "'.$temporary.'" TEMPLATE template0');
$test = null;
$generationFailure = null;
$bin = 'C:/Program Files/PostgreSQL/17/bin/';
$out = $root.'/backend/database/baseline';
if (! is_dir($out)) {
    mkdir($out, 0777, true);
}
$run = function (string $executable, array $arguments) use ($bin, $config, $temporary): void {
    $process = new Process(array_merge([$bin.$executable, '-h', '127.0.0.1', '-p', (string) $config['port'], '-U', $config['username'], '-d', $temporary], $arguments), null, ['PGPASSWORD' => $config['password']]);
    $process->setTimeout(180);
    $process->run();
    if (! $process->isSuccessful()) {
        // No imprimir errores SQL que puedan incluir datos o valores sensibles.
        throw new RuntimeException($executable.' falló en la BD temporal; código '.$process->getExitCode());
    }
};
try {
    $run('psql.exe', ['--no-psqlrc', '--single-transaction', '--set', 'ON_ERROR_STOP=1', '--file', $schemaPath]);
    // Solo en la BD temporal de extracción: permite leer las referencias a empleados
    // que no forman parte del export base. La conexión termina al salir de psql.
    $run('psql.exe', ['--no-psqlrc', '--single-transaction', '--set', 'ON_ERROR_STOP=1', '-c', 'SET session_replication_role=replica', '--file', $dataPath]);
    $test = new PDO('pgsql:host=127.0.0.1;port='.$config['port'].';dbname='.$temporary, $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $usage = auditarUsoTablas($test, $root);
    $tables = array_values(array_filter($actual, fn ($table) => $usage[$table]['decision'] !== 'retirar'));
    $originalCharColumns = $test->query("SELECT c.table_schema, c.table_name, c.column_name, c.character_maximum_length FROM information_schema.columns c JOIN information_schema.tables t ON t.table_schema=c.table_schema AND t.table_name=c.table_name WHERE c.data_type='character' AND t.table_type='BASE TABLE' AND c.table_schema IN ('dbo','adq','public') ORDER BY c.table_schema,c.table_name,c.ordinal_position")->fetchAll(PDO::FETCH_ASSOC);
    $foreign = $test->query("SELECT ns.nspname || '.' || rel.relname AS child, pn.nspname || '.' || parent.relname AS parent FROM pg_constraint c JOIN pg_class rel ON rel.oid=c.conrelid JOIN pg_namespace ns ON ns.oid=rel.relnamespace JOIN pg_class parent ON parent.oid=c.confrelid JOIN pg_namespace pn ON pn.oid=parent.relnamespace WHERE c.contype='f'")->fetchAll(PDO::FETCH_ASSOC);
    $remaining = $tables;
    $ordered = [];
    while ($remaining) {
        $before = count($remaining);
        foreach ($remaining as $index => $table) {
            $parents = array_column(array_filter($foreign, fn ($fk) => $fk['child'] === $table && $fk['parent'] !== $table && in_array($fk['parent'], $tables, true)), 'parent');
            if (! array_diff($parents, $ordered)) {
                $ordered[] = $table;
                unset($remaining[$index]);
            }
        }
        if (count($remaining) === $before) {
            throw new RuntimeException('Dependencias cíclicas entre tablas base.');
        }
    }
    $snapshot = ['format' => 1, 'source_sha256' => hash_file('sha256', $dataPath), 'tables' => []];
    $counts = [];
    foreach ($ordered as $table) {
        [$schema, $name] = explode('.', $table);
        $rows = $test->query('SELECT * FROM "'.$schema.'"."'.$name.'"')->fetchAll(PDO::FETCH_ASSOC);
        $query = $test->prepare("SELECT a.attname FROM pg_constraint c CROSS JOIN LATERAL unnest(c.conkey) WITH ORDINALITY AS k(attnum, ord) JOIN pg_attribute a ON a.attrelid=c.conrelid AND a.attnum=k.attnum WHERE c.conrelid=CAST(? AS regclass) AND c.contype='p' ORDER BY k.ord");
        $query->execute([$table]);
        $key = $query->fetchAll(PDO::FETCH_COLUMN);
        $charColumns = array_column(array_filter($originalCharColumns, fn ($column) => $table === $column['table_schema'].'.'.$column['table_name']), 'column_name');
        foreach ($rows as &$row) {
            foreach ($charColumns as $column) {
                if (is_string($row[$column])) {
                    // Eliminar solo espacios ASCII de relleno, nunca tabs ni
                    // saltos de línea, y nunca modificar columnas VARCHAR.
                    $row[$column] = rtrim($row[$column], ' ');
                }
            }
            if ($table === 'dbo.d2_configuracion') {
                $row['created_by'] = null;
                $row['updated_by'] = null;
            }
            foreach ($row as $column => $value) {
                $concept = $row['concepto'] ?? $row['nombre'] ?? '';
                $pattern = '/password|contrase[ñn]a|clave|secret|token|credential|api[_-]?key|private[_-]?key/i';
                if (($value !== null && preg_match($pattern, $column)) || ($column === 'valor' && $value !== null && preg_match($pattern, (string) $concept))) {
                    throw new RuntimeException('Revisar un posible secreto en '.$table.' antes de generar fuentes versionadas.');
                }
            }
        }
        unset($row);
        usort($rows, fn ($a, $b) => strcmp(json_encode($a), json_encode($b)));
        if ($table === 'dbo.ad_departamento') {
            // Jerarquía interna: los padres deben existir antes que sus hijos.
            $pendingRows = $rows;
            $rows = [];
            $loadedIds = [];
            while ($pendingRows) {
                $before = count($pendingRows);
                foreach ($pendingRows as $index => $row) {
                    $parent = $row['padre_id'];
                    if ($parent === null || in_array($parent, $loadedIds, true) || $parent === $row['id_depto']) {
                        $rows[] = $row;
                        $loadedIds[] = $row['id_depto'];
                        unset($pendingRows[$index]);
                    }
                }
                if (count($pendingRows) === $before) {
                    throw new RuntimeException('Jerarquía de departamentos incompleta o cíclica.');
                }
            }
        }
        $snapshot['tables'][] = ['name' => $table, 'primary_key' => $key, 'rows' => $rows];
        $counts[$table] = count($rows);
    }
    $query = null;
    $removedEmployeeColumns = array_map(fn ($day) => 's'.$day, range(1, 30));
    // No hay lecturas/escrituras actuales ni dependencias: la programación
    // diaria se conserva en d2_programacion, no en ad_empleado.
    $test->exec('ALTER TABLE dbo.ad_empleado '.implode(', ', array_map(fn ($column) => 'DROP COLUMN "'.$column.'"', $removedEmployeeColumns)));
    $removedTables = array_keys(array_filter($usage, fn ($row) => $row['decision'] === 'retirar' && $row['kind'] !== 'v'));
    $removedViews = array_keys(array_filter($usage, fn ($row) => $row['decision'] === 'retirar' && $row['kind'] === 'v'));
    // RESTRICT permite detectar dependencias no previstas, sin borrarlas con CASCADE.
    if ($removedViews) {
        $test->exec('DROP VIEW '.implode(', ', $removedViews).' RESTRICT');
    }
    if ($removedTables) {
        $test->exec('DROP TABLE '.implode(', ', $removedTables).' RESTRICT');
    }
    $originalCharColumns = array_values(array_filter($originalCharColumns, fn ($column) => ! in_array($column['table_schema'].'.'.$column['table_name'], $removedTables, true)));
    $run('pg_dump.exe', ['--schema-only', '--no-owner', '--no-privileges', '--exclude-table=dbo.migrations', '--exclude-table=public.migrations', '--exclude-table=dbo.migrations_id_seq', '--exclude-table=public.migrations_id_seq', '--file', $out.'/estructura-inicial.sql']);
    $schemaSql = file_get_contents($out.'/estructura-inicial.sql');
    $schemaSql = preg_replace('/^\\\\(?:unrestrict|restrict) .*\R/m', '', $schemaSql);
    $schemaSql = preg_replace('/^SET transaction_timeout = 0;\R/m', '', $schemaSql);
    $schemaSql = str_replace(['CREATE SCHEMA dbo;', 'CREATE SCHEMA adq;'], ['CREATE SCHEMA IF NOT EXISTS dbo;', 'CREATE SCHEMA IF NOT EXISTS adq;'], $schemaSql);
    $schemaSql = preg_replace('/\bcharacter\((\d+)\)/', 'character varying($1)', $schemaSql);
    // bpchar es el nombre interno PostgreSQL de CHAR. Las expresiones de
    // vistas también deben retornar VARCHAR, incluidas sus constantes.
    $schemaSql = preg_replace('/::bpchar\b/', '::character varying', $schemaSql);
    $schemaSql = str_replace('ADD CONSTRAINT uq_supervisor_area UNIQUE (id_depto);', 'ADD CONSTRAINT uq_supervisor_area_depto_sup UNIQUE (id_depto, id_supervisor);', $schemaSql, $supervisorChanges);
    if ($supervisorChanges !== 1) {
        throw new RuntimeException('Revisar el constraint de supervisores de la estructura fuente.');
    }
    file_put_contents($out.'/estructura-inicial.sql', $schemaSql);
    file_put_contents($out.'/datos-base.json', json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
    $contract = BaselineRrhhService::inspect($test);
    $supervisorConstraint = $contract['dbo.supervisor_area']['constraints']['uq_supervisor_area'];
    $supervisorConstraint['columns'] = ['id_depto', 'id_supervisor'];
    unset($contract['dbo.supervisor_area']['constraints']['uq_supervisor_area']);
    $contract['dbo.supervisor_area']['constraints']['uq_supervisor_area_depto_sup'] = $supervisorConstraint;
    file_put_contents($out.'/contrato-estructura.json', json_encode($contract, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
    file_put_contents($out.'/uso-tablas.json', json_encode($usage, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
    exportarUsoTablasCsv($usage, $root.'/docs/USO_TABLAS.csv');
    file_put_contents($root.'/docs/tablas-datos-base.txt', implode("\n", array_keys($counts))."\n");
    file_put_contents($out.'/manifest.json', json_encode([
        'schema_source_sha256' => hash_file('sha256', $schemaPath),
        'data_source_sha256' => hash_file('sha256', $dataPath),
        'schema_sha256' => hash_file('sha256', $out.'/estructura-inicial.sql'),
        'data_sha256' => hash_file('sha256', $out.'/datos-base.json'),
        'contract_sha256' => hash_file('sha256', $out.'/contrato-estructura.json'),
        'usage_sha256' => hash_file('sha256', $out.'/uso-tablas.json'),
        'schema_tables' => count(array_filter($contract, fn ($row) => $row['kind'] !== 'v')),
        'rows' => array_sum($counts),
        'counts' => $counts,
        'char_to_varchar' => $originalCharColumns,
        'removed_columns' => ['dbo.ad_empleado' => $removedEmployeeColumns],
        'removed_tables' => $removedTables,
        'removed_views' => $removedViews,
        'transformations' => ['Excluidos dbo.migrations y public.migrations: Laravel crea su historial propio.', 'Sin órdenes de psql ni transaction_timeout específico de PG17.', 'created_by y updated_by de d2_configuracion son NULL en la semilla independiente de empleados.', 'Todas las columnas CHAR(n) se crean como VARCHAR(n); también se ajustan casts bpchar de las vistas.', 'Se elimina solo el relleno ASCII final en los datos base de las columnas originalmente CHAR.', 'supervisor_area usa UNIQUE(id_depto,id_supervisor), sin el UNIQUE(id_depto) antiguo.', 'Eliminadas s1..s30 exclusivamente de ad_empleado; d2_programacion conserva sus columnas diarias.'],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
    echo json_encode(['result' => 'OK', 'tables' => count($counts), 'rows' => array_sum($counts), 'empty_tables' => count(array_filter($counts, fn ($count) => $count === 0))], JSON_PRETTY_PRINT)."\n";
} catch (Throwable $failure) {
    $generationFailure = $failure;
} finally {
    $test = null;
    $pdo->exec('DROP DATABASE "'.$temporary.'"');
}
if ($generationFailure) {
    fwrite(STDERR, 'Generación fallida: '.$generationFailure->getMessage()."\n");
    exit(1);
}
