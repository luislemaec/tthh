<?php
namespace App\Models\Transporte;

use App\Models\Empleado;
use Illuminate\Database\Eloquent\Model;

class SolicitudMov extends Model
{
    protected $connection = 'pgsql';
    protected $table      = 'dbo.trans_solicitud_mov';

    protected $fillable = [
        'id_emp_solicitante', 'motivo', 'fecha_movilizacion', 'hora_salida',
        'hora_retorno', 'lugar_salida', 'lugar_destino', 'num_personas',
        'estado', 'vehiculo_id', 'id_emp_conductor', 'observacion',
        'km_salida', 'km_retorno', 'hoja_ruta_observacion', 'fecha_completado',
        'id_emp_responsable', 'fecha_aprobacion', 'fecha_negacion',
    ];

    public function solicitante()
    {
        return $this->belongsTo(Empleado::class, 'id_emp_solicitante', 'id_emp');
    }

    public function conductor()
    {
        return $this->belongsTo(Empleado::class, 'id_emp_conductor', 'id_emp');
    }

    public function vehiculo()
    {
        return $this->belongsTo(Vehiculo::class, 'vehiculo_id');
    }

    public function responsable()
    {
        return $this->belongsTo(Empleado::class, 'id_emp_responsable', 'id_emp');
    }
}
