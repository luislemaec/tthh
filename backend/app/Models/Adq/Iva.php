<?php
namespace App\Models\Adq;

use Illuminate\Database\Eloquent\Model;

class Iva extends Model
{
    protected $table = 'adq.iva';

    protected $fillable = ['descripcion', 'porcentaje', 'activo', 'fecha_vigencia'];

    protected $casts = ['activo' => 'boolean', 'porcentaje' => 'float'];
}
