<?php

use App\Services\DatosBaseInicialesService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

$root = dirname(__DIR__);
require $root.'/backend/vendor/autoload.php';
$app = require $root.'/backend/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$config = config('database.connections.pgsql');
if (app()->environment() !== 'local' || $config['host'] !== '127.0.0.1' || ! empty($config['url'])) {
    throw new RuntimeException('La prueba de transición solo admite PostgreSQL local explícito.');
}
$source = $argv[1] ?? $root.'/backend/storage/app/baseline-source/estructura_BDD_RRHH.sql';
if (! is_file($source)) {
    throw new RuntimeException('Indicar el export original de estructura.');
}
$maintenance = new PDO('pgsql:host=127.0.0.1;port='.$config['port'].';dbname='.$config['database'], $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$temporary = 'rrhh_baseline_transition_'.bin2hex(random_bytes(6));
$maintenance->exec('CREATE DATABASE "'.$temporary.'" TEMPLATE template0');
$connection = null;
$failure = null;
try {
    config(['database.connections.pgsql.database' => $temporary]);
    DB::purge('pgsql');
    $connection = DB::connection('pgsql');
    $sql = preg_replace('/^\\\\(?:unrestrict|restrict) .*\R/m', '', file_get_contents($source));
    $connection->unprepared($sql);
    $connection->select('SELECT set_config(?, ?, false)', ['search_path', 'dbo']);
    $history = json_decode(file_get_contents($root.'/backend/database/baseline/historial-consolidado.json'), true, 512, JSON_THROW_ON_ERROR);
    $repository = app('migration.repository');
    $repository->setSource('pgsql');
    foreach ($history['retired_migrations'] as $migration) {
        if ($migration['required_history'] ?? true) {
            $repository->log($migration['name'], 1);
        }
    }
    app(DatosBaseInicialesService::class)->cargar();
    $concept = $connection->table('dbo.d2_configuracion')->orderBy('concepto')->value('concepto');
    $connection->table('dbo.d2_configuracion')->where('concepto', $concept)->update(['valor' => '__preservar_transicion__']);
    $oldChar = (int) $connection->selectOne("SELECT count(*) AS total FROM information_schema.columns c JOIN information_schema.tables t ON t.table_schema=c.table_schema AND t.table_name=c.table_name WHERE c.data_type='character' AND t.table_type='BASE TABLE' AND c.table_schema='dbo'")->total;
    $before = $repository->getRan();
    // Simular un historial incompleto: no debe registrar ningún marcador.
    $missingName = $history['retired_migrations'][0]['name'];
    $connection->table('dbo.migrations')->where('migration', $missingName)->delete();
    if (Artisan::call('rrhh:adoptar-baseline', ['--registrar' => true]) !== 1
        || array_intersect($history['baseline_migrations'], $repository->getRan())) {
        throw new RuntimeException('Un historial incompleto no bloquea la adopción.');
    }
    $repository->log($missingName, 1);
    // Simular una columna incompatible: la validación debe fallar sin registrar.
    $connection->statement('ALTER TABLE adq.unidad_medida ALTER COLUMN nombre TYPE VARCHAR(59)');
    if (Artisan::call('rrhh:adoptar-baseline', ['--registrar' => true]) !== 1
        || array_intersect($history['baseline_migrations'], $repository->getRan())) {
        throw new RuntimeException('Una estructura incompatible no bloquea la adopción.');
    }
    $contract = json_decode(file_get_contents($root.'/backend/database/baseline/contrato-estructura.json'), true, 512, JSON_THROW_ON_ERROR);
    preg_match('/\((\d+)\)/', $contract['adq.unidad_medida']['columns']['nombre']['type'], $length);
    $connection->statement('ALTER TABLE adq.unidad_medida ALTER COLUMN nombre TYPE VARCHAR('.(int) $length[1].')');
    if (Artisan::call('rrhh:adoptar-baseline', ['--actualizar-supervisores' => true]) !== 0
        || array_intersect($history['baseline_migrations'], $repository->getRan())) {
        throw new RuntimeException('La verificación sin --registrar modifica el historial o rechaza la BD original.');
    }
    if (Artisan::call('rrhh:adoptar-baseline', ['--registrar' => true, '--actualizar-supervisores' => true]) !== 0) {
        throw new RuntimeException('Falló la adopción verificada: '.Artisan::output());
    }
    if (array_diff($before, $repository->getRan()) || array_diff($history['baseline_migrations'], $repository->getRan())) {
        throw new RuntimeException('Se perdió el historial anterior o faltan los dos marcadores iniciales.');
    }
    $constraint = $connection->selectOne("SELECT pg_get_constraintdef(oid) AS definition FROM pg_constraint WHERE conrelid='dbo.supervisor_area'::regclass AND conname='uq_supervisor_area_depto_sup'");
    if (! $constraint || $constraint->definition !== 'UNIQUE (id_depto, id_supervisor)') {
        throw new RuntimeException('La transición no actualiza la restricción de supervisores.');
    }
    $adoptedCount = count($repository->getRan());
    if (Artisan::call('rrhh:adoptar-baseline', ['--registrar' => true]) !== 0 || count($repository->getRan()) !== $adoptedCount) {
        throw new RuntimeException('Repetir la adopción duplica el historial.');
    }
    if (Artisan::call('migrate', ['--database' => 'pgsql', '--force' => true]) !== 0) {
        throw new RuntimeException('migrate intenta recrear la base existente: '.Artisan::output());
    }
    if ($connection->table('dbo.d2_configuracion')->where('concepto', $concept)->value('valor') !== '__preservar_transicion__') {
        throw new RuntimeException('La transición sobrescribió un dato institucional.');
    }
    $newChar = (int) $connection->selectOne("SELECT count(*) AS total FROM information_schema.columns c JOIN information_schema.tables t ON t.table_schema=c.table_schema AND t.table_name=c.table_name WHERE c.data_type='character' AND t.table_type='BASE TABLE' AND c.table_schema='dbo'")->total;
    if ($newChar !== $oldChar) {
        throw new RuntimeException('La adopción alteró tipos del esquema existente.');
    }
    $snapshot = json_decode(file_get_contents($root.'/backend/database/baseline/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
    foreach ($snapshot['counts'] as $table => $count) {
        if ($connection->table($table)->count() !== $count) {
            throw new RuntimeException('La transición cambió la cantidad de filas en '.$table);
        }
    }
    echo json_encode(['result' => 'OK', 'missing_history_blocked' => true, 'incompatible_structure_blocked' => true,
        'dry_run_preserves_history' => true, 'adoption_is_repeatable' => true, 'normal_migrate_preserves_data' => true,
        'previous_history_preserved' => count($before), 'base_tables_preserved' => count($snapshot['counts']), 'base_rows_preserved' => $snapshot['rows']], JSON_PRETTY_PRINT)."\n";
} catch (Throwable $exception) {
    $failure = $exception;
} finally {
    $connection = null;
    DB::purge('pgsql');
    config(['database.connections.pgsql' => $config]);
    $maintenance->exec('DROP DATABASE "'.$temporary.'"');
}
if ($failure) {
    fwrite(STDERR, 'Transición fallida: '.strtok($failure->getPrevious()?->getMessage() ?? $failure->getMessage(), "\n")."\n");
    exit(1);
}
