<?php
namespace App\Models\Adq;

use Illuminate\Database\Eloquent\Model;

class Proveedor extends Model
{
    protected $table = 'adq.proveedor';

    protected $fillable = [
        'ruc', 'nombre', 'direccion', 'contacto', 'email', 'telefono', 'estado',
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
