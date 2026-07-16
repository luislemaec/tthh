<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComSolicitudTransporte extends Model
{
    protected $table = 'dbo.com_solicitud_transporte';
    public $timestamps = false;

    protected $fillable = [
        'solicitud_id', 'tipo', 'nombre', 'ruta',
        'salida_fecha', 'salida_hora', 'llegada_fecha', 'llegada_hora', 'orden',
    ];
}
