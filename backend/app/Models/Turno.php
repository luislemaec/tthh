<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Turno extends Model
{
    protected $connection   = "pgsql";
    protected $table        = "dbo.d2_turno";
    protected $primaryKey   = "id_turno";
    public    $incrementing = false;
    public    $timestamps   = false;

    protected $fillable = [
        "id_turno",
        "concepto",
        "hora",
        "id_jornada",
    ];

    public function cabecera()
    {
        return $this->belongsTo(CabTurno::class, "id_turno", "id_turno");
    }
}
