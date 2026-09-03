<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HeRegistro extends Model
{
    protected $connection = "pgsql";
    protected $table      = "dbo.nom_he_registro";
    public    $timestamps = true;

    protected $fillable = [
        "cab_id",
        "id_emp",
        "fecha",
        "hora_inicio",
        "hora_fin",
        "horas_extraordinarias",
        "horas_suplementarias",
        "descripcion",
        "estado",
        "usuario_decision",
        "fecha_decision",
        "observacion",
        // Sin esto, el update() de HorasExtrasController::revisarRegistro() (devolver) descartaba
        // en silencio el incremento de devuelto_count — Laravel no lanza excepción por defecto,
        // solo ignora el campo no-fillable. El badge "Dev. Xv" del frontend nunca subía (2026-09-03).
        "devuelto_count",
    ];

    public function planificacion()
    {
        return $this->belongsTo(HePlanificacionCab::class, "cab_id");
    }

    public function empleado()
    {
        return $this->belongsTo(Empleado::class, "id_emp", "id_emp");
    }
}
