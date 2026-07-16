<?php
namespace App\Models\Transporte;

use Illuminate\Database\Eloquent\Model;

class PlanPreventivoCab extends Model
{
    protected $connection = 'pgsql';
    protected $table      = 'dbo.trans_plan_preventivo_cab';

    protected $fillable = ['vehiculo_id', 'km_hito', 'nombre', 'estado'];

    public function vehiculo()
    {
        return $this->belongsTo(Vehiculo::class, 'vehiculo_id');
    }

    public function actividades()
    {
        return $this->hasMany(PlanPreventivoDet::class, 'cab_id')->orderBy('orden');
    }
}
