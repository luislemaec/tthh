<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class LimpiarProduccion extends Command
{
    protected $signature   = 'bootstrap:limpiar-produccion {--force : Omite la confirmación interactiva}';
    protected $description = 'BORRA (TRUNCATE) todo lo transaccional — uso único al arrancar producción desde un dump de pruebas. Ver Fase 4 del artifact "Despliegue a Producción".';

    // A propósito NO es una migración — una migración corre solo con `php artisan migrate` en
    // CUALQUIER ambiente que tenga el código (incluida pruebas), y esto es destructivo. Este
    // comando exige invocación explícita + confirmación, para que no se dispare por accidente.
    //
    // Tablas a truncar (mismo listado que la Fase 4 del artifact de despliegue / CLAUDE.md
    // "Despliegue a Producción"). A propósito NO incluye: ad_empleado, ad_departamento, roles,
    // admin_usuario_rol (si se borra, nadie entra el día 1), menú, catálogos, ni nada de
    // Tecnología (ti_*) — son datos reales, no transaccionales de prueba.
    private const TABLAS = [
        // Talento Humano
        'dbo.sg_control_persona', 'dbo.web_control_persona',
        'dbo.d2_cuadre_marcacion',
        'dbo.d2_permiso', 'dbo.d2_permiso_documento',
        'dbo.d2_vacacion',
        'dbo.d2_nomina_cierre',
        'dbo.vac_planificacion_cab', 'dbo.vac_planificacion_det',
        'dbo.vac_liquidacion_historico',
        'dbo.nom_he_planificacion_cab', 'dbo.nom_he_planificacion_det', 'dbo.nom_he_registro',
        'dbo.nom_auditoria_log',
        'dbo.acc_accion_personal',
        'dbo.d2_certificado_laboral',
        'dbo.nom_decimo_tercero', 'dbo.nom_decimo_cuarto', 'dbo.nom_fondos_reserva',
        'dbo.nom_rol_pago_cab', 'dbo.nom_rol_pago_det',

        // Adquisiciones (movimientos, no catálogos)
        'adq.orden_compra', 'adq.orden_compra_det',
        'adq.egreso', 'adq.egreso_det',
        'adq.kardex',
        'adq.solicitud_material', 'adq.solicitud_material_det',

        // Transportes (movimientos, no vehículos/planes)
        'dbo.trans_mantenimiento', 'dbo.trans_mantenimiento_actividad',
        'dbo.trans_solicitud_mov',
        'dbo.trans_vale_combustible',

        // Comisiones de Servicios (solicitudes/fichas, no catálogos)
        'dbo.com_solicitud', 'dbo.com_solicitud_servidor', 'dbo.com_solicitud_transporte', 'dbo.com_solicitud_documento',
        'dbo.com_anticipo',
        'dbo.com_informe', 'dbo.com_informe_transporte',
        'dbo.com_ficha_liquidacion',

        // Auditoría legada (distinta de nom_auditoria_log) — AuthController todavía escribe acá
        // en cada login, se detectó al revisar manualmente después del primer TRUNCATE (2026-09-02)
        'dbo.d2_auditoria',

        // Framework (schema public) — sesiones/tokens/cache/colas con datos reales de pruebas.
        // OJO: incluye sessions y personal_access_tokens — correr esto desloguea la sesión activa
        // con la que se está parado en el navegador en ese momento (esperado, no es un error).
        // NO incluye public.migrations — esa se maneja aparte (ver Fase 3 del artifact), nunca
        // se trunca a mano.
        'public.cache', 'public.cache_locks',
        'public.failed_jobs', 'public.job_batches', 'public.jobs',
        'public.password_reset_tokens', 'public.personal_access_tokens', 'public.sessions',
        'public.users',
    ];

    public function handle(): int
    {
        $this->warn('Esto va a hacer TRUNCATE (borrado total e irreversible) de ' . count(self::TABLAS) . ' tablas transaccionales:');
        $this->newLine();
        foreach (self::TABLAS as $t) {
            $this->line("  - {$t}");
        }
        $this->newLine();
        $this->info('Se conservan intactas: ad_empleado, ad_departamento, roles, admin_usuario_rol, menú, catálogos, todo lo de Tecnología (ti_*), y public.migrations.');
        $this->warn('Incluye sessions y personal_access_tokens — esto va a desloguear cualquier sesión activa en el navegador (esperado).');
        $this->newLine();

        if (!$this->option('force')) {
            $this->error('¿YA HICISTE UN BACKUP ADICIONAL justo antes de correr esto? (ver Fase 4 del artifact)');
            if (!$this->confirm('¿Confirmás el TRUNCATE de las tablas de arriba? Esto NO se puede deshacer.', false)) {
                $this->info('Cancelado — no se tocó nada.');
                return Command::SUCCESS;
            }
        }

        $lista = implode(', ', self::TABLAS);

        DB::transaction(function () use ($lista) {
            DB::statement("TRUNCATE TABLE {$lista} CASCADE");
        });

        $this->info('Listo — ' . count(self::TABLAS) . ' tablas truncadas.');
        return Command::SUCCESS;
    }
}
