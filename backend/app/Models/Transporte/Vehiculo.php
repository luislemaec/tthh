<?php
namespace App\Models\Transporte;

use Illuminate\Database\Eloquent\Model;

class Vehiculo extends Model
{
    protected $connection = 'pgsql';
    protected $table      = 'dbo.trans_vehiculo';

    protected $fillable = [
        'placa', 'marca', 'modelo', 'anio', 'chasis',
        'color', 'kilometraje_actual', 'estado', 'numero_motor',
    ];

    public function mantenimientos()
    {
        return $this->hasMany(Mantenimiento::class, 'vehiculo_id');
    }

    public function solicitudesMovilizacion()
    {
        return $this->hasMany(SolicitudMov::class, 'vehiculo_id');
    }
}
