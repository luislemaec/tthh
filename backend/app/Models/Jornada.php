<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Jornada extends Model
{
    protected $connection   = "pgsql";
    protected $table        = "dbo.d2_jornada";
    protected $primaryKey   = "id_jornada";
    public    $timestamps   = false;

    protected $fillable = [
        "id_jornada",
        "descripcion",
        "jornada_ordinaria_maxima",
        "recargo",
        "normal",
        "porc_25",
        "porc_extraordinaria",
        "porc_suplementaria",
    ];
}
