<?php
namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuditoriaService
{
    public static function log(
        string  $tabla,
        mixed   $registroId,
        string  $accion,
        mixed   $datosAnteriores,
        mixed   $datosNuevos,
        Request $request,
        string  $descripcion = null
    ): void {
        try {
            $emp = $request->user();
            DB::table('dbo.nom_auditoria_log')->insert([
                'tabla'            => $tabla,
                'registro_id'      => is_numeric($registroId) ? (int)$registroId : 0,
                'accion'           => $accion,
                'datos_anteriores' => $datosAnteriores ? json_encode($datosAnteriores, JSON_UNESCAPED_UNICODE) : null,
                'datos_nuevos'     => $datosNuevos     ? json_encode($datosNuevos,     JSON_UNESCAPED_UNICODE) : null,
                'usuario_id'       => $emp->id_emp,
                'nombre_usuario'   => trim($emp->apellido_emp . ' ' . $emp->nombre_emp),
                'ip_origen'        => $request->ip(),
                'descripcion'      => $descripcion,
                'created_at'       => now(),
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('AuditoriaService error: ' . $e->getMessage());
        }
    }
}
