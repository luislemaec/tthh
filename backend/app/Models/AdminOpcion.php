<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminOpcion extends Model
{
    protected $connection   = 'pgsql';
    protected $table        = 'dbo.admin_opcion';
    protected $primaryKey   = 'id';
    public    $incrementing = false;
    protected $keyType      = 'string';
    public    $timestamps   = false;

    protected $fillable = [
        'id','descripcion','url','categoria',
        'orden_categoria','secuencia','estado','padre',
    ];
}
