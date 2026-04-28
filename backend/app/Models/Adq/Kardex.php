<?php
namespace App\Models\Adq;

use Illuminate\Database\Eloquent\Model;

class Kardex extends Model
{
    protected $table      = 'adq.kardex';
    protected $connection = 'pgsql';
    public    $timestamps = false;

    protected $fillable = [
        'articulo_id', 'fecha', 'tipo_movimiento', 'referencia_tipo', 'referencia_id',
        'referencia_det_id', 'numero_documento', 'cantidad_entrada', 'cantidad_salida',
        'stock_antes', 'stock_despues', 'precio_antes', 'precio_despues',
        'precio_movimiento', 'subtotal', 'iva_valor', 'total_linea',
        'usuario', 'observacion', 'created_at',
    ];
}
