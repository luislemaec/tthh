<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CertificadoLaboral extends Model
{
    protected $connection = 'pgsql';
    protected $table      = 'dbo.d2_certificado_laboral';

    protected $fillable = [
        'numero', 'id_emp', 'fecha_emision',
        'alfresco_id', 'nombre_archivo', 'usuario_emision',
    ];

    protected $casts = [
        'fecha_emision' => 'date',
    ];

    public function empleado()
    {
        return $this->belongsTo(Empleado::class, 'id_emp', 'id_emp');
    }

    public function emisor()
    {
        return $this->belongsTo(Empleado::class, 'usuario_emision', 'id_emp');
    }
}
