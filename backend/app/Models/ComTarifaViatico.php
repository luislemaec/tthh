<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComTarifaViatico extends Model
{
    protected $table = 'dbo.com_tarifa_viatico';

    protected $fillable = ['descripcion', 'valor_dia', 'tipo', 'activo'];

    protected $casts = [
        'valor_dia' => 'decimal:2',
        'activo'    => 'boolean',
    ];
}
