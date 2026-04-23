<?php
namespace App\Models\Adq;

use Illuminate\Database\Eloquent\Model;

class EgresoDet extends Model
{
    protected $table = 'adq.egreso_det';

    protected $fillable = [
        'egreso_id', 'articulo_id', 'cantidad',
        'precio_unitario', 'precio_anterior',
        'iva_id', 'iva_porcentaje', 'subtotal', 'iva_valor', 'total_linea',
    ];

    public function articulo()
    {
        return $this->belongsTo(Articulo::class, 'articulo_id');
    }
}
