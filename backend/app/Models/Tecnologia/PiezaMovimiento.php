<?php
namespace App\Models\Tecnologia;

use Illuminate\Database\Eloquent\Model;

class PiezaMovimiento extends Model
{
    protected $connection = 'pgsql';
    protected $table      = 'dbo.ti_pieza_movimiento';

    protected $fillable = [
        'pieza_id', 'equipo_id', 'mantenimiento_id', 'fecha_instalacion', 'fecha_retiro',
        'motivo_retiro', 'observacion', 'usuario_instala', 'usuario_retira',
    ];

    public function pieza()
    {
        return $this->belongsTo(Pieza::class, 'pieza_id');
    }

    public function equipo()
    {
        return $this->belongsTo(Equipo::class, 'equipo_id');
    }

    public function mantenimiento()
    {
        return $this->belongsTo(Mantenimiento::class, 'mantenimiento_id');
    }
}
