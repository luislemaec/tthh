<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModalidadLaboral extends Model
{
    protected $table      = 'dbo.d2_modalidad_laboral';
    public    $timestamps = false;
    protected $fillable   = ['nombre', 'estado', 'orden'];
}
