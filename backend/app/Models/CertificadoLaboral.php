<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CertificadoLaboral extends Model
{
    protected $connection = 'pgsql';
    protected $table      = 'dbo.d2_certificado_laboral';

    protected $fillable = [
        'numero', 'id_emp', 'fecha_emision',
        'alfresco_id', 'nombre_archivo', 'usuario_emision',
        'estado', 'observacion_anulacion', 'anulado_en', 'anulado_por',
    ];

    // Genera el siguiente número correlativo del año (DATH-CL-NNN-AAAA). Mismo patrón que
    // Empleado::generarSiguienteId() / AccionPersonal::generarSiguienteNumero() — advisory
    // lock de Postgres para que dos emisiones simultáneas no calculen el mismo número
    // (antes: MAX(...)+1 sin bloqueo, y como `numero` es UNIQUE, la segunda emisión
    // simultánea reventaba con 500 en vez de un error controlado). DEBE llamarse dentro
    // de una transacción activa.
    public static function generarSiguienteNumero(int $anio): string
    {
        DB::statement('SELECT pg_advisory_xact_lock(963258741)');
        $ultimo = self::whereYear('fecha_emision', $anio)
            ->selectRaw("MAX(CAST(SPLIT_PART(numero, '-', 3) AS INTEGER)) as ultimo")
            ->value('ultimo');
        $seq = str_pad(($ultimo ?? 0) + 1, 3, '0', STR_PAD_LEFT);
        return "DATH-CL-{$seq}-{$anio}";
    }

    protected $casts = [
        'fecha_emision' => 'date',
    ];

    public function empleado()
    {
        return $this->belongsTo(Empleado::class, 'id_emp', 'id_emp');
    }

    public function emisor()
    {
        return $this->belongsTo(Empleado::class, 'usuario_emision', 'id_emp');
    }
}
