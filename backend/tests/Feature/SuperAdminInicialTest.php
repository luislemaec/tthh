<?php

namespace Tests\Feature;

use App\Models\Empleado;
use App\Services\AutenticacionService;
use Database\Seeders\SuperAdminInicialSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

class SuperAdminInicialTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Fixtures aislados, sin conectar a la BD institucional.
        config(['database.default' => 'pgsql', 'database.connections.pgsql' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ], 'bootstrap_admin.password' => 'SoloPruebas-123!', 'services.ad.host' => null]);
        DB::purge('pgsql');
        DB::statement("ATTACH DATABASE ':memory:' AS dbo");
        // Las reglas funcionales se verifican aquí; el bloqueo concurrente
        // requiere PostgreSQL real, no esta función del fixture SQLite.
        DB::connection()->getPdo()->sqliteCreateFunction('pg_advisory_xact_lock', fn ($key) => 0);
        DB::statement('CREATE TABLE dbo.ad_empleado (id_emp TEXT PRIMARY KEY, identificacion TEXT UNIQUE, nombre_emp TEXT, apellido_emp TEXT, id_depto INTEGER NOT NULL, estado TEXT, password TEXT)');
        DB::statement('CREATE TABLE dbo.ad_departamento (id_depto INTEGER PRIMARY KEY, estado TEXT)');
        DB::statement('CREATE TABLE dbo.admin_rol (id INTEGER PRIMARY KEY, descripcion TEXT, estado BOOLEAN)');
        DB::statement('CREATE TABLE dbo.admin_usuario_rol (id_emp TEXT, identificacion TEXT, id_rol INTEGER, PRIMARY KEY (id_emp, identificacion, id_rol))');
        DB::table('dbo.ad_departamento')->insert(['id_depto' => 62, 'estado' => 'ACTIVO']);
        DB::table('dbo.admin_rol')->insert(['id' => 1, 'descripcion' => 'ADMINISTRADOR', 'estado' => true]);
    }

    public function test_crea_bcrypt_y_autentica_con_cedula_completa(): void
    {
        $this->seed(SuperAdminInicialSeeder::class);
        $empleado = Empleado::where('identificacion', '0123467890')->firstOrFail();
        $this->assertSame('0123467890', $empleado->identificacion);
        $this->assertSame(62, (int) $empleado->id_depto);
        $this->assertSame('bcrypt', password_get_info($empleado->password)['algoName']);
        $this->assertTrue(Hash::check('SoloPruebas-123!', $empleado->password));
        $this->assertSame($empleado->id_emp, app(AutenticacionService::class)->autenticar('0123467890', 'SoloPruebas-123!')?->id_emp);
        $this->assertNull(app(AutenticacionService::class)->autenticar('0123467890', 'incorrecta'));
        $this->assertSame(1, DB::table('dbo.admin_usuario_rol')->where('id_emp', $empleado->id_emp)->where('id_rol', 1)->count());
    }

    public function test_repeticion_conserva_password_cambiado_y_no_duplica(): void
    {
        $this->seed(SuperAdminInicialSeeder::class);
        $empleado = Empleado::firstOrFail();
        $nuevoHash = Hash::make('UnaClaveNueva-987!');
        $empleado->password = $nuevoHash;
        $empleado->save();
        config(['bootstrap_admin.password' => null]);
        $this->seed(SuperAdminInicialSeeder::class);
        $this->assertSame(1, Empleado::count());
        $this->assertSame(1, DB::table('dbo.admin_usuario_rol')->count());
        $this->assertSame($nuevoHash, $empleado->fresh()->password);
    }

    public function test_rechaza_catalogo_incompleto_sin_crear_usuario(): void
    {
        DB::table('dbo.ad_departamento')->delete();
        try {
            $this->seed(SuperAdminInicialSeeder::class);
            $this->fail('Debe rechazar el departamento inexistente.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('catálogos', $exception->getMessage());
        }
        $this->assertSame(0, Empleado::count());
    }

    public function test_no_promueve_cedula_existente_con_datos_distintos(): void
    {
        Empleado::create(['id_emp' => '00001', 'identificacion' => '0123467890',
            'nombre_emp' => 'Otro', 'apellido_emp' => 'Empleado', 'id_depto' => 62, 'estado' => 'ACTIVO']);
        try {
            $this->seed(SuperAdminInicialSeeder::class);
            $this->fail('Debe conservar la cuenta existente.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('datos diferentes', $exception->getMessage());
        }
        $this->assertSame('Otro', Empleado::first()->nombre_emp);
        $this->assertSame(0, DB::table('dbo.admin_usuario_rol')->count());
    }

    public function test_exige_password_privado_para_creacion(): void
    {
        config(['bootstrap_admin.password' => null]);
        try {
            $this->seed(SuperAdminInicialSeeder::class);
            $this->fail('Debe exigir contraseña explícita.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('BOOTSTRAP_ADMIN_PASSWORD', $exception->getMessage());
        }
        $this->assertSame(0, Empleado::count());
    }
}
