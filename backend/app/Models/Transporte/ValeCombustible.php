<?php
namespace App\Models\Transporte;

use Illuminate\Database\Eloquent\Model;
use App\Models\Empleado;

class ValeCombustible extends Model
{
    protected $connection = 'pgsql';
    protected $table      = 'dbo.trans_vale_combustible';

    protected $fillable = [
        'numero', 'gasolinera', 'id_emp_conductor', 'vehiculo_id',
        'kilometraje', 'fecha',
        'glns_extra', 'pu_extra', 'valor_extra',
        'glns_super', 'pu_super', 'valor_super',
        'glns_diesel', 'pu_diesel', 'valor_diesel',
        'estado',
    ];

    public function conductor()
    {
        return $this->belongsTo(Empleado::class, 'id_emp_conductor', 'id_emp');
    }

    public function vehiculo()
    {
        return $this->belongsTo(Vehiculo::class, 'vehiculo_id');
    }
}
