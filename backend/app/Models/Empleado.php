<?php
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Empleado extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $connection   = "pgsql";
    protected $table        = "dbo.ad_empleado";
    protected $primaryKey   = "id_emp";
    public    $incrementing = false;
    protected $keyType      = "string";
    public    $timestamps   = false;

    protected $fillable = [
        "id_emp", "identificacion", "nombre_emp", "apellido_emp",
        "id_depto", "estado", "tipo_contrato", "jornada_id", "id_jornada",
        "fecha_ingreso", "fecha_salida", "ubicacion", "modalidad_marcacion", "sueldo",
        "nivel", "cargo_empleado", "telefono", "calle_y_numero",
        "campo_supervisor", "modalidad_laboral",
        "partida_individual", "partida_presupuestaria", "estado_puesto",
        "grupo_ocupacional", "proceso_institucional",
        "acumula_fondos_reserva", "acumula_decimo_tercero", "acumula_decimo_cuarto",
        "puede_solicitar_vehiculo",
        "created_at", "created_by", "updated_at", "updated_by",
    ];

    protected $hidden = ["password", "clave"];

    public function departamento()
    {
        return $this->belongsTo(Departamento::class, "id_depto", "id_depto");
    }

    public function roles()
    {
        return $this->hasMany(AdminUsuarioRol::class, "id_emp", "id_emp");
    }

    public function emails()
    {
        return $this->hasMany(EmpleadoMail::class, "id_emp", "id_emp")
                     ->where("estado", "ACTIVO");
    }

    public function jornada()
    {
        return $this->belongsTo(Jornada::class, "id_jornada", "id_jornada");
    }

    public function getAuthPassword()
    {
        return $this->password;
    }

    public function getExtensionAttribute($value)
    {
        return trim($value);
    }
}
