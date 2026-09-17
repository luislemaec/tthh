<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vacacion extends Model
{
    protected $connection   = "pgsql";
    protected $table        = "dbo.d2_vacacion";
    protected $primaryKey   = "secuencial_clave";
    public    $incrementing = true;
    public    $timestamps   = false;

    protected $fillable = [
        "id_emp",
        "fecha_hora",
        "nombre_emp",
        "fecha_inicial",
        "fecha_final",
        "hora_desde",
        "hora_hasta",
        "observaciones",
        "todo_dia",
        "estado_permiso",
        "observacion_negacion",
        "ip",
        "aprobado_en",
        "aprobado_por",
        "updated_at",
        "updated_by",
        "backup_id",
        "backup_nombre",
        "requiere_informe",
        "informe_estado",
        "informe_fecha",
        "informe_por",
    ];

    public function empleado()
    {
        return $this->belongsTo(Empleado::class, "id_emp", "id_emp");
    }

    public function aprobador()
    {
        return $this->belongsTo(Empleado::class, "aprobado_por", "id_emp");
    }

    public function informador()
    {
        return $this->belongsTo(Empleado::class, "informe_por", "id_emp");
    }
}
