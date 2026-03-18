<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CabTurno extends Model
{
    protected $connection   = "pgsql";
    protected $table        = "dbo.d2_cab_turno";
    protected $primaryKey   = "id_turno";
    public    $incrementing = true;
    public    $timestamps   = false;

    protected $fillable = [
        "id_turno",
        "descripcion",
        "color",
        "horas_normales",
        "horas_25",
    ];

    public function horarios()
    {
        return $this->hasMany(Turno::class, "id_turno", "id_turno")
                     ->orderByRaw("CASE concepto
                        WHEN 'ENTRADA' THEN 1
                        WHEN 'SALIDA AL LUNCH' THEN 2
                        WHEN 'ENTRADA DEL LUNCH' THEN 3
                        WHEN 'SALIDA' THEN 4
                        ELSE 5 END");
    }
}
