<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccionPersonal extends Model
{
    protected $connection = "pgsql";
    protected $table      = "dbo.acc_accion_personal";
    protected $primaryKey = "id_accion";

    protected $fillable = [
        "numero_accion", "tipo_accion", "fecha_elaboracion",
        "id_emp", "id_emp_titular",
        "fecha_inicio", "fecha_fin", "motivacion",
        "actual_cargo", "actual_grupo_ocup", "actual_grado",
        "actual_remuneracion", "actual_partida", "actual_proceso_inst",
        "propuesto_cargo", "propuesto_grupo_ocup", "propuesto_grado",
        "propuesto_remuneracion", "propuesto_partida", "propuesto_proceso_inst",
        "diferencial", "estado", "creado_por", "pdf_firmado",
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
