<?php
namespace App\Models\Adq;

use Illuminate\Database\Eloquent\Model;

class Egreso extends Model
{
    protected $table = 'adq.egreso';

    protected $fillable = [
        'numero_secuencial', 'anio', 'direccion', 'empleado_id', 'empleado_nombre',
        'estado', 'observacion', 'subtotal', 'iva_valor', 'total',
        'usuario_registro', 'usuario_despacho', 'fecha_despacho',
        'motivo_reverso', 'usuario_reverso', 'fecha_reverso',
    ];

    public function detalles()
    {
        return $this->hasMany(EgresoDet::class, 'egreso_id');
    }
}
