<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComCoeficientePais extends Model
{
    protected $connection = 'pgsql';
    protected $table      = 'dbo.com_coeficiente_pais';

    protected $fillable = ['pais', 'region', 'coeficiente', 'activo'];
}
