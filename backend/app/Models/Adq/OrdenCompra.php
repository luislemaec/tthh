<?php
namespace App\Models\Adq;

use Illuminate\Database\Eloquent\Model;

class OrdenCompra extends Model
{
    protected $table = 'adq.orden_compra';

    protected $fillable = [
        'proveedor_id', 'fecha', 'estado', 'observacion',
        'usuario_registro', 'usuario_recepcion', 'fecha_recepcion',
    ];

    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }

    public function detalles()
    {
        return $this->hasMany(OrdenCompraDet::class, 'orden_id');
    }
}
