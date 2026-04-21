<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CabeceraVacacion extends Model
{
    protected $connection   = "pgsql";
    protected $table        = "dbo.d2_cabecera_vacacion";
    protected $primaryKey   = "id_emp";
    public    $incrementing = false;
    protected $keyType      = "string";
    public    $timestamps   = false;

    protected $fillable = [
        "id_emp", "dias_adicionales", "fecha_proceso",
        "total_dias_tomados", "total_fin_semana", "total_tomados",
        "dias_x_tomar_normal", "dias_x_tomar_fin_semana",
        "dias_totales", "venta_normal", "venta_adicional",
    ];
}
