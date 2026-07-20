<?php
namespace App\Models\Tecnologia;

use Illuminate\Database\Eloquent\Model;

class Pieza extends Model
{
    protected $connection = 'pgsql';
    protected $table      = 'dbo.ti_pieza';

    protected $fillable = [
        'codigo', 'serie', 'descripcion', 'fecha_entrega', 'estado', 'equipo_id',
        'observaciones', 'created_by', 'updated_by',
    ];

    public function equipo()
    {
        return $this->belongsTo(Equipo::class, 'equipo_id');
    }

    public function movimientos()
    {
        return $this->hasMany(PiezaMovimiento::class, 'pieza_id')->orderBy('fecha_instalacion', 'desc');
    }
}
