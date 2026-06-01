<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmpleadoHijo extends Model
{
    protected $connection = 'pgsql';
    protected $table      = 'dbo.ad_empleado_hijo';
    public    $timestamps = false;

    protected $fillable = ['id_emp', 'nombre', 'fecha_nacimiento', 'created_at'];

    protected $casts = ['fecha_nacimiento' => 'date'];

    public function empleado()
    {
        return $this->belongsTo(Empleado::class, 'id_emp', 'id_emp');
    }
}
