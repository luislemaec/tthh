<?php
namespace App\Models\Adq;

use Illuminate\Database\Eloquent\Model;

class SolicitudMaterialDet extends Model
{
    protected $table = 'adq.solicitud_material_det';

    protected $fillable = [
        'solicitud_id', 'articulo_id', 'cantidad_solicitada', 'cantidad_autorizada',
    ];

    public function articulo()
    {
        return $this->belongsTo(Articulo::class, 'articulo_id');
    }
}
