<?php
namespace App\Models\Tecnologia;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Equipo extends Model
{
    protected $connection = 'pgsql';
    protected $table      = 'dbo.ti_equipo';

    protected $fillable = [
        'codigo_bien', 'tipo_equipo_id', 'marca', 'modelo', 'descripcion', 'serie',
        'estado', 'condicion', 'fecha_ingreso', 'vida_util_anios', 'ubicacion',
        'ultimo_mantenimiento', 'observaciones', 'created_by', 'updated_by',
    ];

    protected $appends = ['vida_util_vencida'];

    public function getVidaUtilVencidaAttribute(): bool
    {
        if (!$this->fecha_ingreso || !$this->vida_util_anios) return false;
        return Carbon::parse($this->fecha_ingreso)->addYears((int) $this->vida_util_anios)->lte(now());
    }

    public function tipoEquipo()
    {
        return $this->belongsTo(TipoEquipo::class, 'tipo_equipo_id');
    }

    public function asignaciones()
    {
        return $this->hasMany(Asignacion::class, 'equipo_id')->orderBy('fecha_asignacion', 'desc');
    }

    public function asignacionActiva()
    {
        return $this->hasOne(Asignacion::class, 'equipo_id')->whereNull('fecha_devolucion');
    }
}
