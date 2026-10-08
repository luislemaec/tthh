<?php

namespace Tests\Feature;

use App\Models\Empleado;
use App\Services\AutenticacionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AutenticacionLocalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'pgsql', 'database.connections.pgsql' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ], 'services.ad.host' => null]);
        DB::purge('pgsql');
        DB::statement("ATTACH DATABASE ':memory:' AS dbo");
        DB::statement('CREATE TABLE dbo.ad_empleado (id_emp TEXT PRIMARY KEY, identificacion TEXT, estado TEXT, password TEXT)');
    }

    #[DataProvider('passwordsSinBcrypt')]
    public function test_password_local_invalido_rechaza_acceso_sin_excepcion(?string $hash): void
    {
        DB::table('dbo.ad_empleado')->insert([
            'id_emp' => '00001', 'identificacion' => '0123467890',
            'estado' => 'ACTIVO', 'password' => $hash,
        ]);

        $service = app(AutenticacionService::class);
        $this->assertNull($service->autenticar('0123467890', 'Clave-Prueba-123!'));
        $this->assertFalse($service->validarPasswordLocal('Clave-Prueba-123!', $hash));
        $this->assertSame($hash, Empleado::findOrFail('00001')->password);
    }

    public static function passwordsSinBcrypt(): array
    {
        return [
            'nulo' => [null],
            'vacio' => [''],
            'texto plano' => ['Clave-Prueba-123!'],
            'hash MD5' => [md5('Clave-Prueba-123!')],
            'hash truncado' => ['$2y$04$incompleto'],
            'otro algoritmo' => [password_hash('Clave-Prueba-123!', PASSWORD_ARGON2ID)],
        ];
    }

    #[DataProvider('variantesBcrypt')]
    public function test_bcrypt_correcto_permite_fallback_y_password_incorrecto_se_rechaza(string $variante): void
    {
        $hash = Hash::driver('bcrypt')->make('Clave-Prueba-123!');
        $hash = '$'.$variante.'$'.substr($hash, 4);
        DB::table('dbo.ad_empleado')->insert([
            'id_emp' => '00001', 'identificacion' => '0123467890',
            'estado' => 'ACTIVO', 'password' => $hash,
        ]);

        $service = app(AutenticacionService::class);
        $this->assertSame('00001', $service->autenticar('0123467890', 'Clave-Prueba-123!')?->id_emp);
        $this->assertNull($service->autenticar('0123467890', 'incorrecta'));
    }

    public static function variantesBcrypt(): array
    {
        return ['2y PHP' => ['2y'], '2a PostgreSQL' => ['2a'], '2b' => ['2b']];
    }

    public function test_empleado_inactivo_o_inexistente_no_autentica(): void
    {
        DB::table('dbo.ad_empleado')->insert([
            'id_emp' => '00001', 'identificacion' => '0123467890',
            'estado' => 'INACTIVO', 'password' => Hash::driver('bcrypt')->make('Clave-Prueba-123!'),
        ]);

        $service = app(AutenticacionService::class);
        $this->assertNull($service->autenticar('0123467890', 'Clave-Prueba-123!'));
        $this->assertNull($service->autenticar('9999999999', 'Clave-Prueba-123!'));
    }
}
