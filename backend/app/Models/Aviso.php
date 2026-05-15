<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Aviso extends Model
{
    protected $table      = 'dbo.d2_aviso';
    protected $fillable   = ['texto', 'activo', 'orden'];
    protected $casts      = ['activo' => 'boolean'];
}
