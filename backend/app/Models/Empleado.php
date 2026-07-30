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
        "nivel", "cargo_empleado", "telefono", "extension", "calle_y_numero",
        "campo_supervisor", "modalidad_laboral",
        "partida_individual", "partida_presupuestaria", "estado_puesto",
        "grupo_ocupacional", "proceso_institucional",
        "acumula_fondos_reserva", "acumula_decimo_tercero", "acumula_decimo_cuarto",
        "programa", "actividad",
        "puede_solicitar_vehiculo",
        "sexo", "tipo_sangre",
        "num_sercop", "fecha_vence_sercop",
        "grupo_vulnerable_id", "grupo_prioritario_id",
        "tiene_discapacidad", "tipo_discapacidad_id", "porcentaje_discapacidad",
        "tiene_enfermedad_catastrofica", "enfermedad_catastrofica_id",
        "tiene_persona_sustituta", "sustituta_alfresco_id", "sustituta_nombre_archivo", "sustituta_fecha_caducidad",
        "num_hijos_mayores",
        "motivo_salida", "motivo_reactivacion", "institucion_comision",
        "banco", "tipo_cuenta", "numero_cuenta",
        "foto",
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

    public function hijos()
    {
        return $this->hasMany(EmpleadoHijo::class, 'id_emp', 'id_emp')->orderBy('fecha_nacimiento');
    }

    public function getFotoUrlAttribute(): ?string
    {
        $ruta = $this->foto;
        return $ruta ? \Illuminate\Support\Facades\Storage::disk('public')->url($ruta) : null;
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
