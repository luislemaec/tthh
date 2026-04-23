<?php
namespace App\Models\Adq;

use Illuminate\Database\Eloquent\Model;

class OrdenCompra extends Model
{
    protected $table = 'adq.orden_compra';

    protected $fillable = [
        'proveedor_id', 'fecha', 'estado', 'observacion',
        'tipo_ingreso', 'proceso_contratacion', 'tipo_documento',
        'numero_documento', 'fecha_documento',
        'subtotal', 'iva_valor', 'total',
        'usuario_registro', 'usuario_recepcion', 'fecha_recepcion',
        'numero_secuencial', 'anio',
        'motivo_reverso', 'usuario_reverso', 'fecha_reverso',
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
