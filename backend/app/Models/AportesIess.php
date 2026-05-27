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
        "iece_patronal", "iece_personal", "secap_patronal", "secap_personal",
        "fecha_desde", "fecha_hasta",
        "created_at", "created_by", "updated_at", "updated_by",
    ];

    protected $casts = [
        "aporte_individual" => "decimal:2",
        "aporte_patronal"   => "decimal:2",
        "iece_patronal"     => "decimal:2",
        "iece_personal"     => "decimal:2",
        "secap_patronal"    => "decimal:2",
        "secap_personal"    => "decimal:2",
        "fecha_desde"       => "date",
        "fecha_hasta"       => "date",
    ];
}
