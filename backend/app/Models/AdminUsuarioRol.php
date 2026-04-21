<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminUsuarioRol extends Model
{
    protected $connection   = "pgsql";
    protected $table        = "dbo.admin_usuario_rol";
    protected $primaryKey   = null;
    public    $incrementing = false;
    public    $timestamps   = false;

    protected $fillable = ["id_emp", "identificacion", "id_rol"];

    public function rol()
    {
        return $this->belongsTo(AdminRol::class, "id_rol", "id");
    }
}
