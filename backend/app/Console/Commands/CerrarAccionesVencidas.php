<?php
namespace App\Console\Commands;

use App\Models\AccionPersonal;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CerrarAccionesVencidas extends Command
{
    protected $signature   = 'cerrar:acciones-vencidas';
    protected $description = 'Cierra automáticamente acciones de personal (SUBROGACION, VACACIONES, COMISION DE SERVICIOS) cuya fecha_fin ya pasó';

    // Antes esto vivía como efecto secundario de AccionPersonalController::index() (un
    // GET) — violaba la semántica HTTP y dependía de que alguien abriera la pantalla de
    // Acciones de Personal para que corriera. Ahora es un comando programado, mismo
    // patrón que procesar:cuadre. De paso se agrega COMISION DE SERVICIOS, que el index()
    // viejo no cerraba (inconsistente con SUBROGACION/VACACIONES).
    public function handle(): int
    {
        $vencidas = AccionPersonal::whereIn('tipo_accion', ['SUBROGACION', 'VACACIONES', 'COMISION DE SERVICIOS'])
            ->where('estado', 'ACTIVO')
            ->whereNotNull('fecha_fin')
            ->where('fecha_fin', '<', now()->toDateString())
            ->get();

        foreach ($vencidas as $accion) {
            $anterior = ['estado' => 'ACTIVO', 'fecha_fin' => (string) $accion->fecha_fin];
            $accion->update(['estado' => 'FINALIZADO', 'updated_at' => now()]);

            // Sin Request (comando CLI) no se puede usar AuditoriaService::log() (exige
            // Request no-nulo) — mismo criterio que LOGIN/LOGOUT: insert directo.
            try {
                DB::table('dbo.nom_auditoria_log')->insert([
                    'tabla'            => 'dbo.acc_accion_personal',
                    'registro_id'      => $accion->getKey(),
                    'accion'           => 'AUTO_CERRAR',
                    'datos_anteriores' => json_encode($anterior, JSON_UNESCAPED_UNICODE),
                    'datos_nuevos'     => json_encode(['estado' => 'FINALIZADO'], JSON_UNESCAPED_UNICODE),
                    'usuario_id'       => 'SISTEMA',
                    'nombre_usuario'   => 'Sistema (cerrar:acciones-vencidas)',
                    'ip_origen'        => null,
                    'descripcion'      => "Cierre automático de acción vencida: {$accion->tipo_accion} ({$accion->numero_accion})",
                    'created_at'       => now(),
                ]);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('CerrarAccionesVencidas auditoría error: ' . $e->getMessage());
            }
        }

        // Timestamp de la última corrida — mismo criterio que ULTIMO_CUADRE_PROCESADO,
        // para poder detectar en el Dashboard si el cron de schedule:run dejó de correr.
        DB::table('dbo.d2_configuracion')->updateOrInsert(
            ['concepto' => 'ULTIMO_CIERRE_ACCIONES'],
            ['valor' => now()->toDateTimeString(), 'descripcion' => 'Última vez que corrió cerrar:acciones-vencidas (automático, no editar a mano)']
        );

        $this->info(count($vencidas) . ' acción(es) cerrada(s) automáticamente.');
        return Command::SUCCESS;
    }
}
