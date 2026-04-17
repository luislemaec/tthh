<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HeRegistro extends Model
{
    protected $connection = "pgsql";
    protected $table      = "dbo.nom_he_registro";
    public    $timestamps = true;

    protected $fillable = [
        "cab_id",
        "id_emp",
        "fecha",
        "hora_inicio",
        "hora_fin",
        "horas_extraordinarias",
        "horas_suplementarias",
        "descripcion",
        "estado",
        "usuario_decision",
        "fecha_decision",
        "observacion",
    ];

    public function planificacion()
    {
        return $this->belongsTo(HePlanificacionCab::class, "cab_id");
    }

    public function empleado()
    {
        return $this->belongsTo(Empleado::class, "id_emp", "id_emp");
    }
}
