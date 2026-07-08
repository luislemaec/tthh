<?php
namespace App\Models\Tecnologia;

use Illuminate\Database\Eloquent\Model;

class MantenimientoDetalle extends Model
{
    protected $connection = 'pgsql';
    protected $table      = 'dbo.ti_mantenimiento_detalle';
    public    $timestamps = false;

    protected $fillable = ['mantenimiento_id', 'actividad_id', 'realizado'];

    public function actividad()
    {
        return $this->belongsTo(ActividadMantenimiento::class, 'actividad_id');
    }
}
