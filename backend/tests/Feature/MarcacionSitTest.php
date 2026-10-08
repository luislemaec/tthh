<?php

namespace Tests\Feature;

use App\Models\Empleado;
use App\Services\AutenticacionService;
use App\Services\SesionSitService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MarcacionSitTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Escenario GPS determinista: independiente de los valores del ambiente.
        // Las pruebas de parametrización verifican los cambios desde la BD.
        config(['marcacion' => [
            'app_latitud' => -0.1805373,
            'app_longitud' => -78.4892070,
            'app_radio_m' => 50,
            'app_precision_m' => 25,
            'app_antiguedad_s' => 30,
        ]]);
        // Fixtures aislados: nunca conectar ni migrar la BD institucional.
        config(['database.default' => 'pgsql', 'database.connections.pgsql' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ], 'cache.default' => 'array']);
        DB::purge('pgsql');
        DB::statement("ATTACH DATABASE ':memory:' AS dbo");
        DB::statement("ATTACH DATABASE ':memory:' AS public");
        foreach ([
            'CREATE TABLE dbo.ad_empleado (id_emp TEXT PRIMARY KEY, identificacion TEXT, estado TEXT, nombre_emp TEXT, apellido_emp TEXT, modalidad_marcacion TEXT, ubicacion TEXT, id_depto TEXT)',
            'CREATE TABLE dbo.ad_departamento (id_depto TEXT PRIMARY KEY, nombre_depto TEXT)',
            'CREATE TABLE public.personal_access_tokens (id INTEGER PRIMARY KEY AUTOINCREMENT, tokenable_type TEXT, tokenable_id TEXT, name TEXT, token TEXT UNIQUE, abilities TEXT, last_used_at TEXT, expires_at TEXT, created_at TEXT, updated_at TEXT)',
            'CREATE TABLE dbo.admin_usuario_rol (id_emp TEXT, id_rol INTEGER)',
            'CREATE TABLE dbo.admin_rol (id INTEGER PRIMARY KEY, descripcion TEXT, estado BOOLEAN)',
            'CREATE TABLE dbo.admin_rol_opcion (id_rol INTEGER, id_opcion INTEGER)',
            'CREATE TABLE dbo.admin_opcion (id INTEGER PRIMARY KEY, url TEXT, estado BOOLEAN, descripcion TEXT, categoria TEXT, orden_categoria INTEGER, secuencia INTEGER, padre INTEGER)',
            'CREATE TABLE dbo.d2_auditoria (fecha_hora TEXT, usuario INTEGER, concepto TEXT, id_emp TEXT, ip TEXT)',
            'CREATE TABLE dbo.ad_empleado_teletrabajo (id_emp TEXT, fecha_desde TEXT, fecha_hasta TEXT)',
            'CREATE TABLE dbo.d2_configuracion (concepto TEXT, valor TEXT)',
            'CREATE TABLE dbo.sg_control_persona (secuencial INTEGER PRIMARY KEY AUTOINCREMENT, identificador INTEGER, clasificacion TEXT, nro_documento TEXT, lugar TEXT, fecha_hora TEXT, concepto TEXT, motivo TEXT, tipo_marcacion TEXT, ip TEXT, ubicacion TEXT, procesado TEXT, origen TEXT)',
            'CREATE TABLE dbo.nom_auditoria_log (tabla TEXT, registro_id INTEGER, accion TEXT, datos_anteriores TEXT, datos_nuevos TEXT, usuario_id TEXT, nombre_usuario TEXT, ip_origen TEXT, descripcion TEXT, created_at TEXT)',
        ] as $sql) {
            DB::statement($sql);
        }
        DB::table('dbo.admin_rol')->insert(['id' => 1, 'descripcion' => 'EMPLEADO', 'estado' => true]);
        DB::table('dbo.admin_opcion')->insert(['id' => 1, 'url' => 'asistencia', 'estado' => true]);
        DB::table('dbo.admin_rol_opcion')->insert(['id_rol' => 1, 'id_opcion' => 1]);
        DB::table('dbo.d2_configuracion')->insert(['concepto' => 'vlans_permitidas', 'valor' => '127.0.0.']);
        $this->travelTo(now()->startOfDay()->addHours(8));
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    private function empleado(string $id = '00001', string $modalidad = 'PRESENCIAL'): Empleado
    {
        $emp = Empleado::create(['id_emp' => $id, 'identificacion' => $id, 'estado' => 'ACTIVO',
            'nombre_emp' => 'Prueba', 'apellido_emp' => 'Local', 'modalidad_marcacion' => $modalidad]);
        DB::table('dbo.admin_usuario_rol')->insert(['id_emp' => $id, 'id_rol' => 1]);

        return $emp;
    }

    private function sesion(Empleado $emp, bool $app = true): string
    {
        $this->app['auth']->forgetGuards();

        return app(SesionSitService::class)->emitir($emp, $app)['token'];
    }

    private function estado(string $token)
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token)->getJson('/api/asistencia/mi-estado');
    }

    private function marcar(string $token, array $datos)
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token)->postJson('/api/asistencia/marcar', $datos);
    }

    private function gps(string $token, array $cambios = []): array
    {
        return ['concepto' => 'ENTRADA', 'desafio' => $this->estado($token)->assertOk()->json('desafio'),
            'ubicacion' => array_merge(['latitud' => -0.1805373, 'longitud' => -78.4892070,
                'precision' => 10, 'capturada_en' => now()->toIso8601String(), 'simulada' => false], $cambios)];
    }

    public function test_sesiones_independientes_y_reemplazo_del_mismo_canal(): void
    {
        $emp = $this->empleado();
        $web = $this->sesion($emp, false);
        $app = $this->sesion($emp);
        $appNuevo = $this->sesion($emp);
        $this->estado($app)->assertUnauthorized();
        $this->estado($appNuevo)->assertOk();
        $this->estado($web)->assertOk();
        $webNuevo = $this->sesion($emp, false);
        $this->estado($web)->assertUnauthorized();
        $this->estado($webNuevo)->assertOk();
        $this->estado($appNuevo)->assertOk();
        $this->assertSame(2, $emp->tokens()->count());
    }

    public function test_login_movil_reutiliza_autenticacion_y_responde_minimo(): void
    {
        $emp = $this->empleado();
        $this->mock(AutenticacionService::class)
            ->shouldReceive('autenticar')->once()->with('00001', 'prueba')->andReturn($emp);
        $response = $this->postJson('/api/mobile/login', ['identificacion' => '00001', 'password' => 'prueba'])
            ->assertOk()->assertJsonPath('capacidades.marcacion', true)
            ->assertJsonMissingPath('empleado.numero_cuenta')->assertJsonMissingPath('menu');
        $this->assertSame(now()->addMinutes(15)->toIso8601String(), $response->json('expires_at'));
    }

    public function test_https_obligatorio_para_login_movil_en_produccion(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        $this->postJson('/api/mobile/login', ['identificacion' => '00001', 'password' => 'prueba'])
            ->assertForbidden();
    }

    public function test_quince_minutos_absolutos_para_ambos_canales(): void
    {
        $emp = $this->empleado();
        $web = $this->sesion($emp, false);
        $app = $this->sesion($emp);
        $this->travel(14)->minutes();
        $this->estado($web)->assertOk();
        $this->estado($app)->assertOk();
        $this->travel(1)->minutes();
        $this->estado($web)->assertUnauthorized();
        $this->estado($app)->assertUnauthorized();
    }

    public function test_token_movil_no_accede_a_datos_generales(): void
    {
        $token = $this->sesion($this->empleado());
        $this->withToken($token)->getJson('/api/me')->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/mobile/session')->assertOk()->assertJsonMissingPath('empleado.identificacion');
    }

    public function test_movil_presencial_geolocalizacion_y_origen(): void
    {
        $token = $this->sesion($this->empleado());
        $this->marcar($token, ['concepto' => 'ENTRADA'])->assertUnprocessable();
        $this->marcar($token, $this->gps($token))->assertCreated()->assertJsonPath('marcacion.origen', 'APP');
        $this->marcar($token, $this->gps($token))->assertUnprocessable();
        $this->assertSame(1, DB::table('dbo.sg_control_persona')->count());
    }

    public function test_rechaza_precision_distancia_lectura_antigua_y_simulacion(): void
    {
        $token = $this->sesion($this->empleado());
        foreach ([['precision' => 26], ['latitud' => -0.1815373],
            ['capturada_en' => now()->subSeconds(31)->toIso8601String()], ['simulada' => true]] as $cambio) {
            $this->marcar($token, $this->gps($token, $cambio))->assertUnprocessable();
        }
        $this->assertSame(0, DB::table('dbo.sg_control_persona')->count());
    }

    public function test_distingue_lectura_antigua_y_fecha_adelantada_sin_ampliar_limites(): void
    {
        $token = $this->sesion($this->empleado());
        $this->marcar($token, $this->gps($token, ['capturada_en' => now()->addSeconds(20)->toIso8601String()]))
            ->assertUnprocessable()->assertJsonPath('message', 'La fecha de la ubicación está 20 segundos por delante de SIT. Sincroniza la fecha y hora del celular y de la PC que ejecuta SIT.');
        $this->marcar($token, $this->gps($token, ['capturada_en' => now()->subSeconds(31)->toIso8601String()]))
            ->assertUnprocessable()->assertJsonPath('message', 'La lectura GPS tiene 31 segundos de antigüedad; el máximo es 30. Obtén una lectura nueva. Si las horas difieren, sincroniza el celular y la PC que ejecuta SIT.');
        $this->assertSame(0, DB::table('dbo.sg_control_persona')->count());
    }

    public function test_radio_parametrizado_y_desafio_no_reutilizable(): void
    {
        $token = $this->sesion($this->empleado());
        DB::table('dbo.d2_configuracion')->insert(['concepto' => 'app_radio_m', 'valor' => '5']);
        $this->marcar($token, $this->gps($token))->assertUnprocessable();
        DB::table('dbo.d2_configuracion')->where('concepto', 'app_radio_m')->update(['valor' => '50']);
        $datos = $this->gps($token);
        $this->marcar($token, $datos)->assertCreated();
        $datos['concepto'] = 'SALIDA AL LUNCH';
        $this->marcar($token, $datos)->assertUnprocessable();
    }

    public function test_limites_gps_configurados_en_bd_prevalecen_y_se_respetan(): void
    {
        DB::table('dbo.d2_configuracion')->insert([
            ['concepto' => 'app_radio_m', 'valor' => '1000'],
            ['concepto' => 'app_precision_m', 'valor' => '100'],
            ['concepto' => 'app_antiguedad_s', 'valor' => '200'],
        ]);
        $token = $this->sesion($this->empleado());
        foreach ([['precision' => 101], ['latitud' => -0.2005373],
            ['capturada_en' => now()->subSeconds(201)->toIso8601String()]] as $cambio) {
            $this->marcar($token, $this->gps($token, $cambio))->assertUnprocessable();
        }
        $this->assertSame(0, DB::table('dbo.sg_control_persona')->count());

        // Fuera de los límites del escenario base, pero dentro de los de la BD.
        $this->marcar($token, $this->gps($token, [
            'latitud' => -0.1815373,
            'precision' => 50,
            'capturada_en' => now()->subSeconds(200)->toIso8601String(),
        ]))->assertCreated();
        $this->assertSame(1, DB::table('dbo.sg_control_persona')->count());
    }

    public function test_equipos_compartidos_y_secuencia_web_app(): void
    {
        $emp = $this->empleado();
        $web = $this->sesion($emp, false);
        $app = $this->sesion($emp);
        $otroWeb = $this->sesion($this->empleado('00002'), false);
        $this->marcar($web, ['concepto' => 'SALIDA'])->assertUnprocessable();
        $this->marcar($web, ['concepto' => 'ENTRADA'])->assertCreated();
        $this->marcar($otroWeb, ['concepto' => 'ENTRADA'])->assertCreated();
        $datos = $this->gps($app);
        $datos['concepto'] = 'SALIDA AL LUNCH';
        $this->marcar($app, $datos)->assertCreated();
        $this->assertSame('ENTRADA DEL LUNCH', $this->estado($web)->json('siguiente'));
    }

    public function test_web_presencial_requiere_red_institucional(): void
    {
        $web = $this->sesion($this->empleado(), false);
        DB::table('dbo.d2_configuracion')->where('concepto', 'vlans_permitidas')->update(['valor' => '192.168.']);
        $this->marcar($web, ['concepto' => 'ENTRADA'])->assertForbidden();
        DB::table('dbo.d2_configuracion')->delete();
        $this->marcar($web, ['concepto' => 'ENTRADA'])->assertForbidden();
    }

    public function test_modalidades_y_autorizacion(): void
    {
        $temporal = $this->sesion($this->empleado('00001', 'TEMPORAL'));
        $this->marcar($temporal, ['concepto' => 'ENTRADA'])->assertCreated();
        $tele = $this->sesion($this->empleado('00002', 'TELETRABAJO'));
        $this->marcar($tele, ['concepto' => 'ENTRADA'])->assertForbidden();
        DB::table('dbo.ad_empleado_teletrabajo')->insert(['id_emp' => '00002', 'fecha_desde' => now()->toDateString(), 'fecha_hasta' => now()->toDateString()]);
        $this->marcar($tele, ['concepto' => 'ENTRADA'])->assertCreated();
        $bio = $this->sesion($this->empleado('00003', 'BIOMETRICO'));
        $this->marcar($bio, ['concepto' => 'ENTRADA'])->assertForbidden();
        DB::table('dbo.admin_usuario_rol')->delete();
        $this->marcar($temporal, ['concepto' => 'SALIDA AL LUNCH'])->assertForbidden();
    }
}
