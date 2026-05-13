<?php
namespace App\Models\Transporte;

use Illuminate\Database\Eloquent\Model;

class PlanPreventivoDet extends Model
{
    protected $connection = 'pgsql';
    protected $table      = 'dbo.trans_plan_preventivo_det';
    public    $timestamps = false;

    protected $fillable = ['cab_id', 'orden', 'actividad'];
}
