<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `d2_modalidad_laboral.codigo` — identificador estable contra el que compara el código
 * del sistema (flujo de saldo negativo de Nombramiento Definitivo, motivos de liquidación
 * de vacaciones), en vez del `nombre` que TH puede renombrar libremente.
 *
 * De paso aplica el renombre pedido por TH: 'Contrato Ocasional' -> 'Contrato de Servicios
 * Ocasionales', tanto en el catálogo como en los empleados que ya lo tenían.
 */
return new class extends Migration {
    // codigo => nombre normalizado (minúsculas, sin tildes, espacios colapsados)
    private const CONOCIDAS = [
        'NOMBRAMIENTO_DEFINITIVO'     => 'nombramiento definitivo',
        'NOMBRAMIENTO_PROVISIONAL'    => 'nombramiento provisional',
        'LIBRE_NOMBRAMIENTO_REMOCION' => 'libre nombramiento y remocion',
        'CONTRATO_OCASIONAL'          => 'contrato ocasional',
        'COMISION_SERVICIOS'          => 'comision de servicios',
    ];

    // á→a é→e í→i ó→o ú→u ñ→n ü→u   (from y to alineados posición a posición)
    private const NORMALIZAR =
        "translate(lower(trim(regexp_replace(coalesce(%s, ''), '\\s+', ' ', 'g'))), 'áéíóúñü', 'aeiounu')";

    public function up(): void
    {
        DB::statement("ALTER TABLE dbo.d2_modalidad_laboral ADD COLUMN IF NOT EXISTS codigo VARCHAR(40)");

        $normNombre = sprintf(self::NORMALIZAR, 'nombre');
        foreach (self::CONOCIDAS as $codigo => $norm) {
            DB::statement(
                "UPDATE dbo.d2_modalidad_laboral SET codigo = ? WHERE codigo IS NULL AND {$normNombre} = ?",
                [$codigo, $norm]
            );
        }

        // Renombre TH: 'Contrato Ocasional' -> 'Contrato de Servicios Ocasionales'
        DB::statement("
            UPDATE dbo.d2_modalidad_laboral
            SET nombre = 'Contrato de Servicios Ocasionales'
            WHERE codigo = 'CONTRATO_OCASIONAL'
        ");
        $normEmp = sprintf(self::NORMALIZAR, 'modalidad_laboral');
        DB::statement("
            UPDATE dbo.ad_empleado
            SET modalidad_laboral = 'Contrato de Servicios Ocasionales'
            WHERE {$normEmp} = 'contrato ocasional'
        ");
    }

    public function down(): void
    {
        DB::statement("
            UPDATE dbo.ad_empleado SET modalidad_laboral = 'Contrato Ocasional'
            WHERE modalidad_laboral = 'Contrato de Servicios Ocasionales'
        ");
        DB::statement("
            UPDATE dbo.d2_modalidad_laboral SET nombre = 'Contrato Ocasional'
            WHERE codigo = 'CONTRATO_OCASIONAL'
        ");
        DB::statement("ALTER TABLE dbo.d2_modalidad_laboral DROP COLUMN IF EXISTS codigo");
    }
};
