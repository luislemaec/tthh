<?php
namespace App\Models\Nomina;

use Illuminate\Database\Eloquent\Model;

class RolPago extends Model
{
    protected $table = 'dbo.nom_rol_pago_cab';

    protected $fillable = [
        'anio', 'mes', 'estado', 'total_empleados',
        'total_bruto', 'total_patronal', 'total_descuentos', 'total_liquido',
        'creado_por', 'fecha_calculo', 'cerrado_por', 'fecha_cierre',
    ];
}
