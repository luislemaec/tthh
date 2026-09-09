<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rellena `dbo.d2_modalidad_laboral.codigo` para las filas que la migración `000109` no
 * pudo mapear porque el `nombre` del catálogo estaba en una variante distinta a la esperada
 * (ej. en pruebas id 4 ya era "CONTRATO DE SERVICIOS OCASIONALES" en vez de "Contrato
 * Ocasional"). Matchea por varios alias normalizados. Solo toca filas con `codigo IS NULL`.
 */
return new class extends Migration {
    // codigo => nombres aceptados (ya normalizados: minúsculas, sin tildes, espacios colapsados)
    private const ALIASES = [
        'NOMBRAMIENTO_DEFINITIVO'     => ['nombramiento definitivo'],
        'NOMBRAMIENTO_PROVISIONAL'    => ['nombramiento provisional'],
        'LIBRE_NOMBRAMIENTO_REMOCION' => ['libre nombramiento y remocion'],
        'CONTRATO_OCASIONAL'          => [
            'contrato ocasional',
            'contrato de servicios ocasionales',
            'contrato de servicios ocasional',
            'contrato ocasional de servicios',
            'servicios ocasionales',
        ],
        'COMISION_SERVICIOS'          => ['comision de servicios', 'comision servicios'],
    ];

    private const NORM_NOMBRE =
        "translate(lower(trim(regexp_replace(coalesce(nombre, ''), '\\s+', ' ', 'g'))), 'áéíóúñü', 'aeiounu')";

    public function up(): void
    {
        foreach (self::ALIASES as $codigo => $nombres) {
            $ph = implode(',', array_fill(0, count($nombres), '?'));
            DB::statement(
                "UPDATE dbo.d2_modalidad_laboral SET codigo = ? WHERE codigo IS NULL AND " . self::NORM_NOMBRE . " IN ({$ph})",
                array_merge([$codigo], $nombres)
            );
        }
    }

    public function down(): void
    {
        // no-op — el down() de 000109 elimina la columna `codigo` completa
    }
};
