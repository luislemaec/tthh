<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmpleadoMail extends Model
{
    protected $connection   = "pgsql";
    protected $table        = "dbo.ad_empleado_mail";
    protected $primaryKey   = "secuencial";
    public    $incrementing = true;
    public    $timestamps   = false;

    protected $fillable = [
        "id_emp",
        "mail",
        "estado",
    ];

    public function empleado()
    {
        return $this->belongsTo(Empleado::class, "id_emp", "id_emp");
    }
}
