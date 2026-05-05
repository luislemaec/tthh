<?php
namespace App\Models\Nomina;

use App\Models\Empleado;
use Illuminate\Database\Eloquent\Model;

class FondosReserva extends Model
{
    protected $connection = 'pgsql';
    protected $table      = 'dbo.nom_fondos_reserva';

    protected $fillable = [
        'anio', 'mes', 'id_emp', 'sueldo_base', 'dias', 'porcentaje', 'valor', 'tipo',
        'estado', 'creado_por', 'fecha_calculo', 'cerrado_por', 'fecha_cierre',
    ];

    protected $casts = [
        'fecha_calculo' => 'datetime',
        'fecha_cierre'  => 'datetime',
    ];

    public function empleado()
    {
        return $this->belongsTo(Empleado::class, 'id_emp', 'id_emp');
    }
}
