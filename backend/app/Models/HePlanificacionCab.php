<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HePlanificacionCab extends Model
{
    protected $connection = "pgsql";
    protected $table      = "dbo.nom_he_planificacion_cab";
    public    $timestamps = false;

    protected $fillable = [
        "id_emp",
        "anio",
        "mes",
        "estado",
        "total_extraordinarias",
        "total_suplementarias",
        "observacion",
        "usuario_registro",
        "fecha_registro",
        "usuario_decision",
        "fecha_decision",
        "pdf_aprobado",
    ];

    public function empleado()
    {
        return $this->belongsTo(Empleado::class, "id_emp", "id_emp");
    }

    public function detalles()
    {
        return $this->hasMany(HePlanificacionDet::class, "cab_id");
    }

    public function registros()
    {
        return $this->hasMany(HeRegistro::class, "cab_id");
    }
}
