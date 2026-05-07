<?php
namespace App\Models\Nomina;

use App\Models\Empleado;
use Illuminate\Database\Eloquent\Model;

class RolPagoDet extends Model
{
    protected $table = 'dbo.nom_rol_pago_det';

    protected $fillable = [
        'cab_id', 'id_emp', 'tipo_contrato', 'rmu_puesto', 'dias', 'valor_rmu',
        'aporte_patronal_pct', 'aporte_patronal',
        'aporte_personal_pct', 'aporte_personal',
        'quirografario', 'hipotecario', 'impuesto_renta', 'supa',
        'total_descuentos', 'liquido',
    ];

    public function empleado()
    {
        return $this->belongsTo(Empleado::class, 'id_emp', 'id_emp');
    }
}
