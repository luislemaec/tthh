<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComInforme extends Model
{
    protected $table = 'dbo.com_informe';

    protected $fillable = [
        'solicitud_id', 'fecha_informe', 'actividades', 'productos',
        'fecha_salida', 'hora_salida', 'fecha_llegada', 'hora_llegada',
        'estado', 'observacion', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'fecha_informe' => 'date',
        'fecha_salida'  => 'date',
        'fecha_llegada' => 'date',
    ];

    public function solicitud()
    {
        return $this->belongsTo(ComSolicitud::class, 'solicitud_id');
    }

    public function transportes()
    {
        return $this->hasMany(ComInformeTransporte::class, 'informe_id')->orderBy('orden');
    }
}
