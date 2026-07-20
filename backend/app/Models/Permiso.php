<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Permiso extends Model
{
    protected $connection   = "pgsql";
    protected $table        = "dbo.d2_permiso";
    protected $primaryKey   = "secuencial_clave";
    public    $incrementing = true;
    public    $timestamps   = false;

    protected $fillable = [
        "fecha_hora",
        "id_emp",
        "razon",
        "fecha_desde",
        "fecha_hasta",
        "hora_desde",
        "hora_hasta",
        "usuario",
        "cargo",
        "sec_permiso",
        "estado_permiso",
        "todo_dia",
        "concepto",
        "observaciones",
        "observacion_negacion",
        "terminal",
        "transmitio",
        "origen",
        "centro_costo_cuadre",
        "disminuir_dias",
        "secuencial",
        "principal",
        "descontable",
        "procedencia",
        "tipo_horario",
        "aprobado_en",
    ];

    public function empleado()
    {
        return $this->belongsTo(Empleado::class, "id_emp", "id_emp");
    }

    public function razonPermiso()
    {
        return $this->belongsTo(Razon::class, "sec_permiso", "secuencial");
    }

    public function aprobador()
    {
        return $this->belongsTo(Empleado::class, "usuario", "id_emp");
    }
}
