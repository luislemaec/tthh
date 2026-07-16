<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Departamento extends Model
{
    protected $connection   = "pgsql";
    protected $table        = "dbo.ad_departamento";
    protected $primaryKey   = "id_depto";
    public    $timestamps   = false;

    protected $fillable = ["id_depto", "nombre_depto", "centro_de_costo", "padre_id", "estado",
                           "created_at", "created_by", "updated_at", "updated_by"];

    public function empleados()
    {
        return $this->hasMany(Empleado::class, "id_depto", "id_depto");
    }

    public function padre()
    {
        return $this->belongsTo(Departamento::class, "padre_id", "id_depto");
    }

    public function supervisorArea()
    {
        return $this->hasOne(Supervisor::class, "id_depto", "id_depto");
    }

    public function hijos()
    {
        return $this->hasMany(Departamento::class, "padre_id", "id_depto");
    }
}
