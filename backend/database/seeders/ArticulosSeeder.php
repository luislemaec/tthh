<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ArticulosSeeder extends Seeder
{
    public function run(): void
    {
        $iva15 = DB::table('adq.iva')->where('porcentaje', 15)->value('id');
        $iva0  = DB::table('adq.iva')->where('porcentaje', 0)->value('id');

        $path = storage_path('app/articulos.csv');
        if (!file_exists($path)) {
            $this->command->error("Archivo no encontrado: $path");
            $this->command->info("Coloca el CSV en: backend/storage/app/articulos.csv");
            return;
        }

        $handle = fopen($path, 'r');
        fgetcsv($handle); // saltar cabecera

        $insertados = 0;
        $omitidos   = 0;
        $yaVistos   = [];

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 10) continue;

            [$codigo, $nombre, $descripcion, $unidad_medida, $nivel1, $nivel2,
             $categoria, $marca, $precio_unitario, $iva_porcentaje, $stock_actual, $estado_fisico]
             = array_pad($row, 12, null);

            $codigo = trim($codigo ?? '');
            if (!$codigo) continue;

            // Ignorar duplicados dentro del mismo CSV
            if (isset($yaVistos[$codigo])) { $omitidos++; continue; }
            $yaVistos[$codigo] = true;

            // Ignorar si ya existe en la BD
            if (DB::table('adq.articulo')->where('codigo', $codigo)->exists()) {
                $omitidos++;
                continue;
            }

            $descripcion  = $this->limpiar($descripcion);
            $marca        = $this->limpiar($marca);
            $nivel1       = trim($nivel1 ?? '') ?: null;
            $nivel2       = trim($nivel2 ?? '') ?: null;

            // Detectar fila desalineada (nombre con comas sin comillas)
            if ($nivel1 !== null && strlen($nivel1) > 2) {
                $this->command->warn("Fila desalineada, omitida — codigo: $codigo nombre con comas sin comillas");
                $omitidos++;
                continue;
            }
            $precioConIva = (float)($precio_unitario ?? 0);
            $stock        = (int)($stock_actual ?? 0);
            $ivaPct       = (float)($iva_porcentaje ?? 0);
            $ivaId        = ($ivaPct >= 14) ? $iva15 : $iva0;
            // El CSV trae precio con IVA incluido → calcular precio sin IVA
            $precio = $ivaPct > 0
                ? round($precioConIva / (1 + $ivaPct / 100), 4)
                : $precioConIva;
            $ef           = trim($estado_fisico ?? 'BUENO') ?: 'BUENO';
            if (!in_array($ef, ['BUENO', 'MALO', 'INSERVIBLE'])) $ef = 'BUENO';

            // Validar nivel2 contra el catálogo — si no existe, ignorarlo
            $itemPresup = null;
            if ($nivel2) {
                $catalogo = DB::table('adq.catalogo_inventario')
                    ->where('nivel2', $nivel2)
                    ->first(['asociacion_presupuestaria']);
                if ($catalogo) {
                    $itemPresup = $catalogo->asociacion_presupuestaria;
                } else {
                    $this->command->warn("nivel2 '$nivel2' no está en catálogo — artículo $codigo se carga sin nivel2");
                    $nivel1 = null;
                    $nivel2 = null;
                }
            }

            DB::table('adq.articulo')->insert([
                'codigo'                 => $codigo,
                'nombre'                 => trim($nombre),
                'descripcion'            => $descripcion,
                'unidad_medida'          => trim($unidad_medida ?? '') ?: null,
                'nivel1'                 => $nivel1,
                'nivel2'                 => $nivel2,
                'item_presupuestario'    => $itemPresup,
                'categoria'              => trim($categoria ?? '') ?: null,
                'marca'                  => $marca,
                'precio_unitario'        => $precio,
                'iva_id'                 => $ivaId,
                'stock_actual'           => $stock,
                'stock_maximo_historico' => $stock,
                'estado'                 => 'ACTIVO',
                'estado_fisico'          => $ef,
                'created_at'             => now(),
                'updated_at'             => now(),
            ]);

            $insertados++;
        }

        fclose($handle);
        $this->command->info("Artículos insertados: $insertados | Omitidos/duplicados: $omitidos");
    }

    private function limpiar(?string $valor): ?string
    {
        $v = trim($valor ?? '');
        return ($v === '' || strtolower($v) === 'ninguna') ? null : $v;
    }
}
