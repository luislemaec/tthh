<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AportesIess extends Model
{
    protected $connection = "pgsql";
    protected $table      = "dbo.d2_aportes_iess";
    protected $primaryKey = "id_aporte";
    public    $timestamps = false;

    protected $fillable = [
        "modalidad", "aporte_individual", "aporte_patronal",
        "fecha_desde", "fecha_hasta", "created_at",
    ];

    protected $casts = [
        "aporte_individual" => "decimal:2",
        "aporte_patronal"   => "decimal:2",
        "fecha_desde"       => "date",
        "fecha_hasta"       => "date",
    ];
}
