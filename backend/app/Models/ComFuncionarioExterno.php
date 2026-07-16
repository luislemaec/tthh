<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComFuncionarioExterno extends Model
{
    protected $connection = 'pgsql';
    protected $table      = 'dbo.com_funcionario_externo';

    protected $fillable = ['cedula', 'nombres', 'cargo', 'banco', 'tipo_cuenta', 'numero_cuenta', 'activo'];
}
