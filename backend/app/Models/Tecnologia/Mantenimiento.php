<?php
namespace App\Models\Tecnologia;

use App\Models\Empleado;
use Illuminate\Database\Eloquent\Model;

class Mantenimiento extends Model
{
    protected $connection = 'pgsql';
    protected $table      = 'dbo.ti_mantenimiento';

    protected $fillable = [
        'equipo_id', 'anio', 'fecha_mantenimiento', 'hora_inicio', 'hora_fin', 'tipo',
        'id_emp_tecnico', 'id_emp_custodio', 'observaciones',
        'acta_alfresco_id', 'acta_nombre_archivo', 'created_by',
        'origen', 'proveedor', 'proceso_contratacion', 'numero_orden_compra', 'lote_externo',
    ];

    public function equipo()
    {
        return $this->belongsTo(Equipo::class, 'equipo_id');
    }

    public function tecnico()
    {
        return $this->belongsTo(Empleado::class, 'id_emp_tecnico', 'id_emp');
    }

    public function custodio()
    {
        return $this->belongsTo(Empleado::class, 'id_emp_custodio', 'id_emp');
    }

    public function detalle()
    {
        return $this->hasMany(MantenimientoDetalle::class, 'mantenimiento_id');
    }
}
