<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiquidacionHistorico extends Model
{
    protected $connection = 'pgsql';
    protected $table      = 'dbo.vac_liquidacion_historico';
    public    $timestamps = false;

    protected $fillable = [
        'id_emp', 'motivo', 'fecha_evento', 'fecha_corte_usada',
        'saldo_inicial', 'acumulado', 'tomados', 'saldo_liquidado',
        'observacion', 'usuario_proceso', 'fecha_registro',
    ];

    public function empleado()
    {
        return $this->belongsTo(Empleado::class, 'id_emp', 'id_emp');
    }
}
