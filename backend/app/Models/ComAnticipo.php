<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComAnticipo extends Model
{
    protected $table = 'dbo.com_anticipo';

    protected $fillable = [
        'solicitud_id', 'monto_solicitado', 'estado',
        'cur_compromiso', 'cur_devengado', 'fecha_pago', 'observacion',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'monto_solicitado' => 'decimal:2',
        'fecha_pago'       => 'date',
    ];
}
