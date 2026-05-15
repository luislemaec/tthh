<?php
namespace App\Models\Adq;

use Illuminate\Database\Eloquent\Model;

class Proveedor extends Model
{
    protected $table = 'adq.proveedor';

    protected $fillable = [
        'ruc', 'nombre', 'direccion', 'contacto', 'email', 'telefono', 'estado',
        'es_proveedor_bienes', 'es_taller', 'orden_compra',
    ];

    public function catalogo()
    {
        return $this->hasMany(ProveedorCatalogo::class, 'proveedor_id');
    }

    public function ordenes()
    {
        return $this->hasMany(OrdenCompra::class, 'proveedor_id');
    }
}
