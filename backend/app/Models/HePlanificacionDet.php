<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HePlanificacionDet extends Model
{
    protected $connection = "pgsql";
    protected $table      = "dbo.nom_he_planificacion_det";
    public    $timestamps = false;

    protected $fillable = [
        "cab_id",
        "actividad",
        "horas_extraordinarias",
        "horas_suplementarias",
    ];

    public function planificacion()
    {
        return $this->belongsTo(HePlanificacionCab::class, "cab_id");
    }
}
