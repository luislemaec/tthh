<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Configuracion extends Model
{
    protected $connection   = "pgsql";
    protected $table        = "dbo.d2_configuracion";
    protected $primaryKey   = "concepto";
    public    $incrementing = false;
    protected $keyType      = "string";
    public    $timestamps   = false;

    protected $fillable = [
        "concepto",
        "valor",
        "descripcion",
        "created_at",
        "created_by",
        "updated_at",
        "updated_by",
    ];
}
