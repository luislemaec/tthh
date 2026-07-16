<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("
            CREATE TABLE dbo.d2_modalidad_laboral (
                id     SERIAL PRIMARY KEY,
                nombre VARCHAR(100) NOT NULL,
                estado VARCHAR(10)  NOT NULL DEFAULT 'ACTIVO',
                orden  SMALLINT     NOT NULL DEFAULT 0
            )
        ");

        $modalidades = [
            'Nombramiento Definitivo',
            'Nombramiento Provisional',
            'Libre Nombramiento y Remoción',
            'Contrato Ocasional',
            'Comisión de Servicios',
        ];

        foreach ($modalidades as $i => $nombre) {
            DB::table('dbo.d2_modalidad_laboral')->insert([
                'nombre' => $nombre,
                'estado' => 'ACTIVO',
                'orden'  => $i + 1,
            ]);
        }
    }

    public function down(): void
    {
        DB::statement("DROP TABLE IF EXISTS dbo.d2_modalidad_laboral");
    }
};
