<?php
namespace App\Models\Transporte;

use App\Models\Empleado;
use Illuminate\Database\Eloquent\Model;

class Mantenimiento extends Model
{
    protected $connection = 'pgsql';
    protected $table      = 'dbo.trans_mantenimiento';

    protected $fillable = [
        'vehiculo_id', 'tipo', 'descripcion', 'id_emp_conductor', 'estado',
        'taller', 'fecha_orden', 'numero_orden', 'observacion_responsable',
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
}
