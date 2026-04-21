<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vacacion extends Model
{
    protected $connection   = "pgsql";
    protected $table        = "dbo.d2_vacacion";
    protected $primaryKey   = "secuencial_clave";
    public    $incrementing = true;
    public    $timestamps   = false;

    protected $fillable = [
        "id_emp",
        "fecha_hora",
        "nombre_emp",
        "fecha_inicial",
        "fecha_final",
        "hora_desde",
        "hora_hasta",
        "observaciones",
        "todo_dia",
        "estado_permiso",
        "observacion_negacion",
        "ip",
    ];

    public function empleado()
    {
        return $this->belongsTo(Empleado::class, "id_emp", "id_emp");
    }
}
