<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModalidadLaboral extends Model
{
    protected $table      = 'dbo.d2_modalidad_laboral';
    public    $timestamps = false;
    protected $fillable   = ['nombre', 'codigo', 'estado', 'orden', 'created_at', 'created_by', 'updated_at', 'updated_by'];

    // Códigos estables de las modalidades con comportamiento especial en el sistema.
    // El código compara SIEMPRE contra estos, nunca contra `nombre` (que TH puede
    // renombrar libremente desde Admin → Modalidad Laboral).
    public const COD_NOMBRAMIENTO_DEFINITIVO     = 'NOMBRAMIENTO_DEFINITIVO';
    public const COD_NOMBRAMIENTO_PROVISIONAL    = 'NOMBRAMIENTO_PROVISIONAL';
    public const COD_LIBRE_NOMBRAMIENTO_REMOCION = 'LIBRE_NOMBRAMIENTO_REMOCION';
    public const COD_CONTRATO_OCASIONAL          = 'CONTRATO_OCASIONAL';
    public const COD_COMISION_SERVICIOS          = 'COMISION_SERVICIOS';

    /**
     * Resuelve el nombre libre guardado en `ad_empleado.modalidad_laboral` al `codigo`
     * estable del catálogo, ignorando mayúsculas/minúsculas, tildes y espacios repetidos.
     * Devuelve null si no hay coincidencia (modalidad desconocida o sin código asignado).
     */
    public static function codigoDe(?string $nombre): ?string
    {
        $n = self::normalizar($nombre);
        return $n === '' ? null : (self::mapa()[$n] ?? null);
    }

    public static function esNombramientoDefinitivo(?string $nombre): bool
    {
        return self::codigoDe($nombre) === self::COD_NOMBRAMIENTO_DEFINITIVO;
    }

    /** Slug para el `codigo` de una modalidad nueva creada desde el Admin. */
    public static function generarCodigo(string $nombre): string
    {
        $s = preg_replace('/[^a-z0-9]+/', '_', self::normalizar($nombre));
        return strtoupper(trim($s, '_')) ?: 'MODALIDAD';
    }

    /** @return array<string,?string> nombre normalizado => codigo (cacheado por request) */
    private static function mapa(): array
    {
        static $mapa = null;
        if ($mapa === null) {
            $mapa = [];
            foreach (static::query()->get(['nombre', 'codigo']) as $m) {
                $mapa[self::normalizar($m->nombre)] = $m->codigo ?: null;
            }
        }
        return $mapa;
    }

    private static function normalizar(?string $s): string
    {
        $s = mb_strtolower(trim((string) $s));
        $s = strtr($s, [
            'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n','ü'=>'u',
            'Á'=>'a','É'=>'e','Í'=>'i','Ó'=>'o','Ú'=>'u','Ñ'=>'n','Ü'=>'u', // por si mb_strtolower no cubre algo
        ]);
        return preg_replace('/\s+/', ' ', $s);
    }
}
