<?php
namespace App\Models\Transporte;

use Illuminate\Database\Eloquent\Model;

class TipoMantenimiento extends Model
{
    protected $connection = 'pgsql';
    protected $table      = 'dbo.trans_tipo_mantenimiento';

    protected $fillable = ['nombre', 'estado'];
}
