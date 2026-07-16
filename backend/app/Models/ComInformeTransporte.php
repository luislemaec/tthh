<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComInformeTransporte extends Model
{
    protected $table = 'dbo.com_informe_transporte';
    public $timestamps = false;

    protected $fillable = [
        'informe_id', 'tipo', 'nombre', 'ruta',
        'salida_fecha', 'salida_hora', 'llegada_fecha', 'llegada_hora', 'orden',
    ];
}
