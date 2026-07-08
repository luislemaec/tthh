<?php
namespace App\Models\Tecnologia;

use Illuminate\Database\Eloquent\Model;

class TipoEquipo extends Model
{
    protected $connection = 'pgsql';
    protected $table      = 'dbo.ti_tipo_equipo';

    protected $fillable = ['nombre', 'estado'];
}
