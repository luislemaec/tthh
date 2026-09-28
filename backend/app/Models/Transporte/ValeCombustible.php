<?php
namespace App\Models\Transporte;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Models\Empleado;

class ValeCombustible extends Model
{
    protected $connection = 'pgsql';
    protected $table      = 'dbo.trans_vale_combustible';

    // Genera el siguiente número de vale correlativo. Mismo patrón de advisory
    // lock que Empleado::generarSiguienteId()/AccionPersonal::generarSiguienteNumero()/
    // CertificadoLaboral::generarSiguienteNumero() — antes era MAX(numero)+1 sin
    // bloqueo, así que dos vales creados casi al mismo tiempo (dos conductores,
    // o el mismo formulario enviado dos veces por doble clic) podían calcular el
    // mismo "siguiente" número. DEBE llamarse dentro de una transacción activa
    // (el lock se libera solo al terminar esa transacción).
    public static function generarSiguienteNumero(int $inicioDefault): int
    {
        DB::statement('SELECT pg_advisory_xact_lock(456789123)');
        $max = self::max('numero') ?? $inicioDefault;
        return $max + 1;
    }

    protected $fillable = [
        'numero', 'gasolinera', 'id_emp_conductor', 'vehiculo_id',
        'kilometraje', 'fecha', 'fecha_comprobante',
        'glns_extra', 'pu_extra', 'valor_extra',
        'glns_super', 'pu_super', 'valor_super',
        'glns_diesel', 'pu_diesel', 'valor_diesel',
        'estado',
    ];

    public function conductor()
    {
        return $this->belongsTo(Empleado::class, 'id_emp_conductor', 'id_emp');
    }

    public function vehiculo()
    {
        return $this->belongsTo(Vehiculo::class, 'vehiculo_id');
    }
}
