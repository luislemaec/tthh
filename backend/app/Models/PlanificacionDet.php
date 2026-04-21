<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanificacionDet extends Model
{
    protected $connection   = 'pgsql';
    protected $table        = 'dbo.vac_planificacion_det';
    public    $timestamps   = false;

    protected $fillable = ['cab_id', 'numero_periodo', 'fecha_inicial', 'fecha_final', 'dias_calculados'];
}
