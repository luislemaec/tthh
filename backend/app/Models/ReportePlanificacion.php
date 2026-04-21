<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportePlanificacion extends Model
{
    protected $table      = 'dbo.vac_reporte_planificacion';
    public    $timestamps = false;

    protected $fillable = [
        'anio',
        'alfresco_node_id',
        'nombre_archivo',
        'fecha_subida',
        'subido_por',
    ];
}
