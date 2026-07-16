<?php
namespace App\Models\Transporte;

use Illuminate\Database\Eloquent\Model;

class Taller extends Model
{
    protected $connection = 'pgsql';
    protected $table      = 'dbo.trans_taller';

    protected $fillable = [
        'nombre', 'ruc', 'direccion', 'correo', 'telefono', 'orden_compra', 'estado',
    ];
}
