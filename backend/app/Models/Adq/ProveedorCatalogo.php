<?php
namespace App\Models\Adq;

use Illuminate\Database\Eloquent\Model;

class ProveedorCatalogo extends Model
{
    protected $table = 'adq.proveedor_catalogo';

    protected $fillable = [
        'proveedor_id', 'descripcion', 'unidad_medida', 'precio_referencial',
    ];

    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }
}
