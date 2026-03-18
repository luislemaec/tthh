<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminRol extends Model
{
    protected $connection = 'pgsql';
    protected $table      = 'dbo.admin_rol';
    protected $primaryKey = 'id';
    public    $timestamps = false;

    protected $fillable = ['descripcion','estado'];

    public function opciones()
    {
        return $this->hasMany(AdminRolOpcion::class, 'id_rol', 'id');
    }
}
