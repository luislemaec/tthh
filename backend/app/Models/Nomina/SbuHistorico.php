<?php
namespace App\Models\Nomina;

use Illuminate\Database\Eloquent\Model;

class SbuHistorico extends Model
{
    protected $connection = 'pgsql';
    protected $table      = 'dbo.nom_sbu_historico';

    protected $fillable = ['anio', 'valor', 'usuario_registro'];
}
