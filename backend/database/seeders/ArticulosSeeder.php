<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Carga artículos desde CSV del usuario.
 * CSV esperado (con encabezado): nivel1,nivel2,descripcion,unidad_medida
 * Colocar el archivo en: storage/app/articulos.csv
 *
 * Uso: php artisan db:seed --class=ArticulosSeeder
 */
class ArticulosSeeder extends Seeder
{
    public function run(): void
    {
        $path = storage_path('app/articulos.csv');

        if (!file_exists($path)) {
            $this->command->error("Archivo no encontrado: $path");
            return;
        }

        $handle = fopen($path, 'r');
        fgetcsv($handle); // saltar encabezado

        $insertados = 0;
        $omitidos   = 0;

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 3) continue;

            $nivel1       = trim($row[0]);
            $nivel2       = trim($row[1]);
            $descripcion  = trim($row[2]);
            $unidad       = isset($row[3]) ? trim($row[3]) : null;

            // Verificar que el nivel2 existe en el catálogo
            $existe = DB::table('adq.catalogo_inventario')->where('nivel2', $nivel2)->exists();
            if (!$existe) {
                $this->command->warn("nivel2 '$nivel2' no encontrado en catálogo, omitido.");
                $omitidos++;
                continue;
            }

            // Evitar duplicados por nivel2
            if (DB::table('adq.articulo')->where('nivel2', $nivel2)->exists()) {
                $omitidos++;
                continue;
            }

            DB::table('adq.articulo')->insert([
                'codigo'               => $nivel2,
                'nombre'               => $descripcion,
                'nivel1'               => $nivel1,
                'nivel2'               => $nivel2,
                'unidad_medida'        => $unidad,
                'stock_actual'         => 0,
                'stock_maximo_historico' => 0,
                'estado'               => 'ACTIVO',
                'created_at'           => now(),
                'updated_at'           => now(),
            ]);
            $insertados++;
        }

        fclose($handle);
        $this->command->info("Artículos cargados: $insertados. Omitidos: $omitidos.");
    }
}
