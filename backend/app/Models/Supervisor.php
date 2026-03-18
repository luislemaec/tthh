<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supervisor extends Model
{
    protected $connection   = "pgsql";
    protected $table        = "dbo.supervisor_area";
    protected $primaryKey   = "id";
    public    $incrementing = true;
    public    $timestamps   = false;

    protected $fillable = [
        "id_depto",
        "id_supervisor",
        "fecha_registro",
        "usuario",
    ];

    public function departamento()
    {
        return $this->belongsTo(Departamento::class, "id_depto", "id_depto");
    }

    public function supervisor()
    {
        return $this->belongsTo(Empleado::class, "id_supervisor", "id_emp");
    }

    // Obtener todos los empleados de este departamento
    public function empleados()
    {
        return $this->hasMany(Empleado::class, "id_depto", "id_depto")
                     ->where("estado", "ACTIVO");
    }
}
