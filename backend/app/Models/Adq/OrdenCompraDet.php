<?php
namespace App\Models\Adq;

use Illuminate\Database\Eloquent\Model;

class OrdenCompraDet extends Model
{
    protected $table = 'adq.orden_compra_det';

    protected $fillable = [
        'orden_id', 'articulo_id', 'cantidad', 'precio_unitario',
    ];

    public function articulo()
    {
        return $this->belongsTo(Articulo::class, 'articulo_id');
    }
}
