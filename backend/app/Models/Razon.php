<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Razon extends Model
{
    protected $connection   = 'pgsql';
    protected $table        = 'dbo.d2_razon';
    protected $primaryKey   = 'secuencial';
    public    $incrementing = true;
    public    $timestamps   = false;

    protected $fillable = [
        'secuencial',
        'descripcion',
        'descontable',
        'tipo_razon',
        'nomina',
        'nomenclatura',
        'leyenda_justificacion',
    ];
}
