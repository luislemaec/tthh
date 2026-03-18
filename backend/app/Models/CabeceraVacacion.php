<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CabeceraVacacion extends Model
{
    protected $connection   = "pgsql";
    protected $table        = "dbo.d2_cabecera_vacacion";
    protected $primaryKey   = "id_emp";
    public    $incrementing = false;
    protected $keyType      = "string";
    public    $timestamps   = false;
}
