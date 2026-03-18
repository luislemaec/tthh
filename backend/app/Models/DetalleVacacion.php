<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetalleVacacion extends Model
{
    protected $connection   = "pgsql";
    protected $table        = "dbo.d2_detalle_vacacion";
    protected $primaryKey   = "secuencial";
    public    $incrementing = true;
    public    $timestamps   = false;
}
