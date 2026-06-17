<?php
namespace App\Models\Adq;

use Illuminate\Database\Eloquent\Model;
use App\Models\Empleado;

class SolicitudMaterial extends Model
{
    protected $table = 'adq.solicitud_material';

    protected $fillable = [
        'id_emp', 'id_depto', 'fecha', 'justificacion', 'estado',
        'usuario_aprobacion', 'fecha_aprobacion',
        'usuario_despacho', 'fecha_despacho', 'observacion_despacho',
    ];

    public function empleado()
    {
        return $this->belongsTo(Empleado::class, 'id_emp', 'id_emp');
    }

    public function aprobador()
    {
        return $this->belongsTo(Empleado::class, 'usuario_aprobacion', 'id_emp');
    }

    public function detalles()
    {
        return $this->hasMany(SolicitudMaterialDet::class, 'solicitud_id');
    }
}
