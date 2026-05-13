<?php
namespace App\Models\Transporte;

use App\Models\Empleado;
use Illuminate\Database\Eloquent\Model;

class Mantenimiento extends Model
{
    protected $connection = 'pgsql';
    protected $table      = 'dbo.trans_mantenimiento';

    protected $fillable = [
        'vehiculo_id', 'tipo', 'tipo_mantenimiento_id', 'descripcion',
        'id_emp_conductor', 'estado', 'km_actual', 'plan_preventivo_id',
        'taller', 'taller_id', 'fecha_orden', 'numero_orden', 'observacion_responsable',
        'fecha_finalizacion', 'id_emp_responsable',
        'motivo_negacion', 'fecha_negacion', 'usuario_negacion',
    ];

    public function vehiculo()
    {
        return $this->belongsTo(Vehiculo::class, 'vehiculo_id');
    }

    public function conductor()
    {
        return $this->belongsTo(Empleado::class, 'id_emp_conductor', 'id_emp');
    }

    public function responsable()
    {
        return $this->belongsTo(Empleado::class, 'id_emp_responsable', 'id_emp');
    }

    public function actividades()
    {
        return $this->hasMany(MantenimientoActividad::class, 'mantenimiento_id')->orderBy('tipo')->orderBy('orden');
    }

    public function tipoMantenimiento()
    {
        return $this->belongsTo(TipoMantenimiento::class, 'tipo_mantenimiento_id');
    }

    public function tallerRel()
    {
        return $this->belongsTo(Taller::class, 'taller_id');
    }
}
