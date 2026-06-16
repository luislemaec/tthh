<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComFichaLiquidacion extends Model
{
    protected $table = 'dbo.com_ficha_liquidacion';

    protected $fillable = [
        'solicitud_id',
        'valor_por_dia', 'dias_viaticos', 'total_viatico',
        'anticipo_viatico', 'anticipo_combustible', 'total_anticipo',
        'justif_alimentacion', 'justif_alojamiento', 'total_justificacion',
        'movilizacion', 'peajes_parqueaderos', 'combustibles',
        'otros_gastos',
        'viaticos_por_pagar', 'devolucion_movilizacion', 'total_a_pagar',
        'tipo_resultado',
        'cur_compromiso', 'cur_devengado',
        'comprobante_devolucion', 'fecha_devolucion',
        'estado', 'observacion',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'valor_por_dia'          => 'decimal:2',
        'total_viatico'          => 'decimal:2',
        'anticipo_viatico'       => 'decimal:2',
        'anticipo_combustible'   => 'decimal:2',
        'total_anticipo'         => 'decimal:2',
        'justif_alimentacion'    => 'decimal:2',
        'justif_alojamiento'     => 'decimal:2',
        'total_justificacion'    => 'decimal:2',
        'movilizacion'           => 'decimal:2',
        'peajes_parqueaderos'    => 'decimal:2',
        'combustibles'           => 'decimal:2',
        'otros_gastos'           => 'decimal:2',
        'viaticos_por_pagar'     => 'decimal:2',
        'devolucion_movilizacion'=> 'decimal:2',
        'total_a_pagar'          => 'decimal:2',
        'fecha_devolucion'       => 'date',
    ];

    public function solicitud()
    {
        return $this->belongsTo(ComSolicitud::class, 'solicitud_id');
    }
}
