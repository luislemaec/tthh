<?php
namespace App\Models\Transporte;

use Illuminate\Database\Eloquent\Model;

class MantenimientoActividad extends Model
{
    protected $connection = 'pgsql';
    protected $table      = 'dbo.trans_mantenimiento_actividad';
    public    $timestamps = false;

    protected $fillable = ['mantenimiento_id', 'tipo', 'actividad', 'orden'];
}
