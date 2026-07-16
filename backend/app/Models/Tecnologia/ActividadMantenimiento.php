<?php
namespace App\Models\Tecnologia;

use Illuminate\Database\Eloquent\Model;

class ActividadMantenimiento extends Model
{
    protected $connection = 'pgsql';
    protected $table      = 'dbo.ti_actividad_mantenimiento';

    protected $fillable = ['nombre', 'orden', 'estado'];
}
