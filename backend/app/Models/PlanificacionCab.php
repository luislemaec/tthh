<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanificacionCab extends Model
{
    protected $connection   = 'pgsql';
    protected $table        = 'dbo.vac_planificacion_cab';
    public    $timestamps   = false;

    protected $fillable = [
        'id_emp', 'anio', 'estado', 'total_dias_planificados',
        'fecha_registro', 'usuario_registro',
        'fecha_decision', 'usuario_decision',
        'observacion', 'replanificada',
    ];

    public function empleado()
    {
        return $this->belongsTo(Empleado::class, 'id_emp', 'id_emp');
    }

    public function periodos()
    {
        return $this->hasMany(PlanificacionDet::class, 'cab_id')->orderBy('numero_periodo');
    }
}
