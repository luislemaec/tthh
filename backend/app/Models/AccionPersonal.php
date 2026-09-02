<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AccionPersonal extends Model
{
    protected $connection = "pgsql";
    protected $table      = "dbo.acc_accion_personal";
    protected $primaryKey = "id_accion";

    // Genera el siguiente numero_accion correlativo del año (formato PREFIJO-AÑO-NNNNN).
    // Mismo patrón que Empleado::generarSiguienteId() — advisory lock de Postgres para
    // que dos "procesar()" simultáneos no calculen el mismo número. numero_accion es el
    // número de documento oficial (Contraloría), un duplicado acá es más grave que un
    // id_emp duplicado. DEBE llamarse dentro de una transacción activa.
    public static function generarSiguienteNumero(string $prefijo, int $anio): string
    {
        DB::statement('SELECT pg_advisory_xact_lock(852963741)');
        $ultimo = self::whereYear('created_at', $anio)
            ->whereNotNull('numero_accion')
            ->max(DB::raw("CAST(SPLIT_PART(numero_accion, '-', 3) AS INTEGER)")) ?? 0;
        $numero = str_pad($ultimo + 1, 5, '0', STR_PAD_LEFT);
        return "{$prefijo}-{$anio}-{$numero}";
    }

    protected $fillable = [
        "numero_accion", "tipo_accion", "fecha_elaboracion",
        "id_emp", "id_emp_titular",
        "fecha_inicio", "fecha_fin", "motivacion",
        "actual_cargo", "actual_grupo_ocup", "actual_grado",
        "actual_remuneracion", "actual_partida", "actual_proceso_inst",
        "propuesto_cargo", "propuesto_grupo_ocup", "propuesto_grado",
        "propuesto_remuneracion", "propuesto_partida", "propuesto_proceso_inst",
        "diferencial", "estado", "creado_por", "pdf_firmado",
        "firmante_th_nombre", "firmante_th_cargo",
        "firmante_autoridad_nombre", "firmante_autoridad_cargo",
        "medio", "especificacion",
    ];

    protected $casts = [
        "fecha_elaboracion"    => "date",
        "fecha_inicio"         => "date",
        "fecha_fin"            => "date",
        "actual_remuneracion"  => "decimal:2",
        "propuesto_remuneracion" => "decimal:2",
        "diferencial"          => "decimal:2",
    ];

    public function empleado()
    {
        return $this->belongsTo(Empleado::class, "id_emp", "id_emp");
    }

    public function titular()
    {
        return $this->belongsTo(Empleado::class, "id_emp_titular", "id_emp");
    }
}
