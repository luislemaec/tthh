<?php

use App\Models\Empleado;
use App\Services\DatosBaseInicialesService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

$root = dirname(__DIR__);
require $root.'/backend/vendor/autoload.php';
$app = require $root.'/backend/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$config = config('database.connections.pgsql');
if (app()->environment() !== 'local' || $config['host'] !== '127.0.0.1' || ! empty($config['url'])) {
    throw new RuntimeException('La verificación solo admite PostgreSQL local explícito.');
}
$maintenance = new PDO('pgsql:host=127.0.0.1;port='.$config['port'].';dbname='.$config['database'], $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$temporary = 'rrhh_baseline_test_'.bin2hex(random_bytes(6));
$maintenance->exec('CREATE DATABASE "'.$temporary.'" TEMPLATE template0');
$connection = null;
$verificationFailure = null;
try {
    config(['database.connections.pgsql.database' => $temporary]);
    DB::purge('pgsql');
    $connection = DB::connection('pgsql');
    $connection->statement('CREATE SCHEMA dbo');
    $exit = Artisan::call('migrate', ['--database' => 'pgsql', '--force' => true]);
    if ($exit !== 0) {
        throw new RuntimeException('Falló la instalación aislada: '.Artisan::output());
    }
    $manifest = json_decode(file_get_contents($root.'/backend/database/baseline/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
    $remainingChar = $connection->selectOne("SELECT count(*) AS total FROM information_schema.columns WHERE data_type='character' AND table_schema IN ('dbo','adq','public')");
    if ((int) $remainingChar->total !== 0) {
        throw new RuntimeException('Quedan columnas CHAR en tablas o vistas de la instalación inicial.');
    }
    foreach ($manifest['char_to_varchar'] as $column) {
        $converted = $connection->selectOne('SELECT data_type,character_maximum_length FROM information_schema.columns WHERE table_schema=? AND table_name=? AND column_name=?', [$column['table_schema'], $column['table_name'], $column['column_name']]);
        if (! $converted || $converted->data_type !== 'character varying' || (int) $converted->character_maximum_length !== (int) $column['character_maximum_length']) {
            throw new RuntimeException('Tipo o longitud incorrectos en una columna convertida a VARCHAR.');
        }
    }
    foreach ($manifest['removed_columns']['dbo.ad_empleado'] as $column) {
        if ($connection->selectOne("SELECT 1 AS found FROM information_schema.columns WHERE table_schema='dbo' AND table_name='ad_empleado' AND column_name=?", [$column])) {
            throw new RuntimeException('Permanece una columna de programación obsoleta en ad_empleado.');
        }
        $programColumn = $connection->selectOne("SELECT data_type FROM information_schema.columns WHERE table_schema='dbo' AND table_name='d2_programacion' AND column_name=?", [$column]);
        if (! $programColumn || $programColumn->data_type !== 'integer') {
            throw new RuntimeException('Se perdió una columna diaria activa de d2_programacion.');
        }
    }
    foreach (array_merge($manifest['removed_tables'], $manifest['removed_views']) as $relation) {
        if ($connection->selectOne('SELECT to_regclass(?) AS relation', [$relation])->relation !== null) {
            throw new RuntimeException('Permanece una relación obsoleta en la instalación inicial: '.$relation);
        }
    }
    foreach ($manifest['counts'] as $table => $expected) {
        if ($connection->table($table)->count() !== $expected) {
            throw new RuntimeException('Cantidad distinta del export en '.$table);
        }
    }
    if ($connection->table('dbo.ad_empleado')->count() !== 0) {
        throw new RuntimeException('La instalación base no debe incluir empleados.');
    }
    $connection->beginTransaction();
    $newEmployee = Empleado::create([
        'id_emp' => '99999', 'identificacion' => '9999999999', 'nombre_emp' => 'PRUEBA', 'apellido_emp' => 'ESQUEMA',
        'id_depto' => $connection->table('dbo.ad_departamento')->min('id_depto'),
    ]);
    if (! $newEmployee->exists || ! $connection->table('dbo.ad_empleado')->where('id_emp', '99999')->exists()) {
        throw new RuntimeException('El modelo Empleado no permite crear registros sin s1..s30.');
    }
    $connection->rollBack();
    $connection->beginTransaction();
    $tokenEmployee = Empleado::create([
        'id_emp' => '99998', 'identificacion' => '9999999998', 'nombre_emp' => 'PRUEBA', 'apellido_emp' => 'TOKEN',
        'id_depto' => $connection->table('dbo.ad_departamento')->min('id_depto'),
    ]);
    $issuedToken = $tokenEmployee->createToken('verificar-baseline', ['*'], now()->addMinutes(15));
    if ($issuedToken->accessToken->getTable() !== 'public.personal_access_tokens' || ! $tokenEmployee->tokens()->exists()) {
        throw new RuntimeException('La tabla de tokens activa no permite autenticar mediante Sanctum.');
    }
    $connection->rollBack();
    Cache::store('database')->put('baseline_verificar_cache', 'OK', 60);
    if (Cache::store('database')->get('baseline_verificar_cache') !== 'OK') {
        throw new RuntimeException('El caché de Laravel no funciona con la estructura depurada.');
    }
    Cache::store('database')->forget('baseline_verificar_cache');
    $snapshot = json_decode(file_get_contents($root.'/backend/database/baseline/datos-base.json'), true, 512, JSON_THROW_ON_ERROR);
    foreach ($snapshot['tables'] as $table) {
        foreach ($table['rows'] as $row) {
            $query = $connection->table($table['name']);
            foreach ($row as $column => $value) {
                $value === null ? $query->whereNull($column) : $query->where($column, $value);
            }
            if (! $query->exists()) {
                throw new RuntimeException('Una fila no coincide con la fuente normalizada: '.$table['name']);
            }
        }
    }
    $repeat = app(DatosBaseInicialesService::class)->cargar();
    if ($repeat !== ['insertados' => 0, 'conservados' => $manifest['rows'], 'tablas' => count($manifest['counts'])]) {
        throw new RuntimeException('La segunda carga duplica u omite datos.');
    }
    $concept = $connection->table('dbo.d2_configuracion')->orderBy('concepto')->value('concepto');
    $connection->beginTransaction();
    $connection->table('dbo.d2_configuracion')->where('concepto', $concept)->update(['valor' => '__baseline_test_preserve__']);
    app(DatosBaseInicialesService::class)->cargar();
    if ($connection->table('dbo.d2_configuracion')->where('concepto', $concept)->value('valor') !== '__baseline_test_preserve__') {
        throw new RuntimeException('La carga sobrescribió un parámetro existente.');
    }
    $connection->rollBack();
    $maxRole = (int) $connection->table('dbo.admin_rol')->max('id');
    $connection->beginTransaction();
    $newId = $connection->table('dbo.admin_rol')->insertGetId(['descripcion' => 'PRUEBA BASELINE', 'estado' => true], 'id');
    if ($newId <= $maxRole) {
        throw new RuntimeException('La secuencia de roles no permite nuevas altas.');
    }
    $connection->rollBack();
    $blocked = false;
    try {
        $migration = require $root.'/backend/database/migrations/0000_01_01_000000_create_initial_rrhh_structure.php';
        $migration->up();
    } catch (RuntimeException $exception) {
        $blocked = str_contains($exception->getMessage(), 'BD nueva');
    }
    if (! $blocked) {
        throw new RuntimeException('La estructura no bloquea una BD existente.');
    }
    if (Artisan::call('rrhh:adoptar-baseline') !== 0) {
        throw new RuntimeException('Una instalación nueva no se reconoce como consolidada: '.str_replace("\n", ' | ', Artisan::output()));
    }
    config(['bootstrap_admin.password' => 'Baseline-Prueba-Temporal-123!', 'services.ad.host' => null]);
    if (Artisan::call('db:seed', ['--class' => 'DatabaseSeeder', '--force' => true]) !== 0 || $connection->table('dbo.admin_rol')->count() !== $manifest['counts']['dbo.admin_rol']) {
        throw new RuntimeException('El seeder principal no es repetible.');
    }
    $constraint = $connection->selectOne("SELECT pg_get_constraintdef(oid) AS definition FROM pg_constraint WHERE conrelid='dbo.supervisor_area'::regclass AND conname='uq_supervisor_area_depto_sup'");
    if (! $constraint || $constraint->definition !== 'UNIQUE (id_depto, id_supervisor)') {
        throw new RuntimeException('La estructura inicial no incorpora la restricción compuesta de supervisores.');
    }
    if (Artisan::call('migrate', ['--database' => 'pgsql', '--force' => true]) !== 0 || count(app('migration.repository')->getRan()) !== 2) {
        throw new RuntimeException('Una instalación nueva requiere migraciones adicionales o no es repetible.');
    }
    $admin = Empleado::where('identificacion', '0123467890')->firstOrFail();
    if ((int) $admin->id_depto !== 62 || password_get_info($admin->password)['algoName'] !== 'bcrypt'
        || app(App\Services\AutenticacionService::class)->autenticar('0123467890', 'Baseline-Prueba-Temporal-123!')?->id_emp !== $admin->id_emp) {
        throw new RuntimeException('La cuenta inicial no autentica o sus datos son incorrectos.');
    }
    $hashInicial = $admin->password;
    Artisan::call('db:seed', ['--class' => 'SuperAdminInicialSeeder', '--force' => true]);
    if (Empleado::where('identificacion', '0123467890')->count() !== 1 || $admin->fresh()->password !== $hashInicial
        || $admin->roles()->where('id_rol', 1)->count() !== 1) {
        throw new RuntimeException('La segunda carga modifica la contraseña o duplica la cuenta o rol inicial.');
    }
    echo json_encode(['superadmin_bcrypt_login_department_role_idempotence' => 'OK', 'result' => 'OK', 'tables_checked' => count($manifest['counts']), 'rows_checked' => $manifest['rows'],
        'char_columns_remaining' => 0, 'varchar_columns_converted' => count($manifest['char_to_varchar']),
        'repeated_load' => $repeat, 'preserves_parameters' => true, 'sequence_allows_new_role' => true,
        'existing_database_blocked' => true, 'supervisor_constraint_in_initial_schema' => true, 'active_migrations' => 2,
        'unused_employee_columns_removed' => 30, 'daily_program_columns_preserved' => 30,
        'employee_model_creation' => 'OK', 'unused_tables_removed' => count($manifest['removed_tables']),
        'unused_views_removed' => count($manifest['removed_views']), 'sanctum_token_creation' => 'OK',
        'laravel_database_cache' => 'OK'], JSON_PRETTY_PRINT)."\n";
} catch (Throwable $failure) {
    $verificationFailure = $failure;
} finally {
    if ($connection && $connection->transactionLevel() > 0) {
        while ($connection->transactionLevel() > 0) {
            $connection->rollBack();
        }
    }
    unset($query, $migration);
    $connection = null;
    DB::purge('pgsql');
    config(['database.connections.pgsql' => $config]);
    $maintenance->exec('DROP DATABASE "'.$temporary.'"');
}
if ($verificationFailure) {
    $diagnostic = $verificationFailure->getPrevious()?->getMessage() ?? $verificationFailure->getMessage();
    fwrite(STDERR, 'Verificación fallida: '.strtok($diagnostic, "\n")."\n");
    exit(1);
}
