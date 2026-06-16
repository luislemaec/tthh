<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComSolicitudServidor extends Model
{
    protected $table = 'dbo.com_solicitud_servidor';
    public $timestamps = false;

    protected $fillable = ['solicitud_id', 'id_emp', 'unidad', 'puesto', 'orden'];

    public function empleado()
    {
        return $this->belongsTo(Empleado::class, 'id_emp', 'id_emp');
    }
}
