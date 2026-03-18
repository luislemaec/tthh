<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ListaFecha extends Model
{
    protected $connection   = "pgsql";
    protected $table        = "dbo.d2_lista_fecha";
    public    $incrementing = false;
    public    $timestamps   = false;

    protected $fillable = [
        "fecha",
        "factor",
        "tipo",
        "color",
        "hora_desde",
        "hora_hasta",
        "ubicacion",
        "hora_25",
        "transmitio",
    ];
}
