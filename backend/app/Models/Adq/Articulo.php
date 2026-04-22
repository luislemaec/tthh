<?php
namespace App\Models\Adq;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Articulo extends Model
{
    protected $table = 'adq.articulo';

    protected $fillable = [
        'codigo', 'nombre', 'descripcion', 'unidad_medida', 'categoria', 'marca',
        'nivel1', 'nivel2', 'item_presupuestario',
        'precio_unitario', 'iva_id',
        'stock_actual', 'stock_maximo_historico', 'estado',
    ];

    public function iva()
    {
        return $this->belongsTo(Iva::class, 'iva_id');
    }

    public function getBajoMinimoAttribute(): bool
    {
        $porcentaje = (float) DB::table('adq.configuracion')
            ->where('concepto', 'porcentaje_stock_minimo')
            ->value('valor') ?? 20;

        if ($this->stock_maximo_historico <= 0) return false;
        return $this->stock_actual <= ($this->stock_maximo_historico * $porcentaje / 100);
    }
}
