<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PeriodoPlanificacion extends Model
{
    protected $connection   = 'pgsql';
    protected $table        = 'dbo.vac_periodo_planificacion';
    public    $timestamps   = false;

    protected $fillable = ['anio', 'fecha_inicio', 'fecha_fin', 'estado'];
}
