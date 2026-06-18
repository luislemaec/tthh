<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ComSolicitudDocumento extends Model
{
    protected $table = 'dbo.com_solicitud_documento';
    public $timestamps = false;

    protected $fillable = [
        'solicitud_id', 'tipo_doc', 'alfresco_id', 'nombre_archivo', 'created_by', 'created_at',
    ];
}
