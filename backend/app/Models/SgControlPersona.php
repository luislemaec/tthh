<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SgControlPersona extends Model
{
    protected $connection   = "pgsql";
    protected $table        = "dbo.sg_control_persona";
    protected $primaryKey   = "secuencial";
    public    $incrementing = true;
    public    $timestamps   = false;

    protected $fillable = [
        "identificador",
        "clasificacion",
        "nro_documento",
        "lugar",
        "fecha_hora",
        "concepto",
        "motivo",
        "tipo_marcacion",
        "ip",
        "ubicacion",
        "procesado",
        "origen",
    ];

    public function empleado()
    {
        return $this->belongsTo(Empleado::class, "nro_documento", "id_emp");
    }
}
