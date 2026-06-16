<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComSolicitud extends Model
{
    protected $table = 'dbo.com_solicitud';

    protected $fillable = [
        'numero_solicitud', 'tipo', 'id_emp', 'id_depto', 'fecha_solicitud',
        'tiene_viaticos', 'tiene_movilizaciones', 'tiene_anticipo',
        'destino', 'unidad_nombre',
        'fecha_salida', 'hora_salida', 'fecha_llegada', 'hora_llegada',
        'descripcion_actividades',
        'banco', 'tipo_cuenta', 'numero_cuenta',
        'estado', 'observacion',
        'num_sistema_exterior', 'resolucion_juridica',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'tiene_viaticos'      => 'boolean',
        'tiene_movilizaciones'=> 'boolean',
        'tiene_anticipo'      => 'boolean',
        'fecha_solicitud'     => 'date',
        'fecha_salida'        => 'date',
        'fecha_llegada'       => 'date',
    ];

    public function empleado()
    {
        return $this->belongsTo(Empleado::class, 'id_emp', 'id_emp');
    }

    public function servidores()
    {
        return $this->hasMany(ComSolicitudServidor::class, 'solicitud_id')->orderBy('orden');
    }

    public function transportes()
    {
        return $this->hasMany(ComSolicitudTransporte::class, 'solicitud_id')->orderBy('orden');
    }

    public function informe()
    {
        return $this->hasOne(ComInforme::class, 'solicitud_id');
    }

    public function anticipo()
    {
        return $this->hasOne(ComAnticipo::class, 'solicitud_id');
    }

    public function fichaLiquidacion()
    {
        return $this->hasOne(ComFichaLiquidacion::class, 'solicitud_id');
    }
}
