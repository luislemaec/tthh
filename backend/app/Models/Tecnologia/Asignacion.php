<?php
namespace App\Models\Tecnologia;

use App\Models\Empleado;
use Illuminate\Database\Eloquent\Model;

class Asignacion extends Model
{
    protected $connection = 'pgsql';
    protected $table      = 'dbo.ti_asignacion';

    protected $fillable = [
        'equipo_id', 'id_emp', 'fecha_asignacion', 'fecha_devolucion',
        'motivo_devolucion', 'observacion', 'usuario_asigna', 'usuario_devolucion',
    ];

    public function equipo()
    {
        return $this->belongsTo(Equipo::class, 'equipo_id');
    }

    public function empleado()
    {
        return $this->belongsTo(Empleado::class, 'id_emp', 'id_emp');
    }
}
