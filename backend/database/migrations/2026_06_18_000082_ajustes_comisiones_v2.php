<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. es_externo en ad_empleado
        Schema::table('dbo.ad_empleado', function (Blueprint $table) {
            $table->boolean('es_externo')->default(false)->after('estado');
        });

        // 2. programa y actividad en com_funcionario_externo
        Schema::table('dbo.com_funcionario_externo', function (Blueprint $table) {
            $table->string('programa', 4)->nullable()->after('numero_cuenta');
            $table->string('actividad', 6)->nullable()->after('programa');
        });

        // 3. Provincias de Ecuador
        Schema::create('dbo.com_provincia', function (Blueprint $table) {
            $table->increments('id');
            $table->string('nombre', 100);
        });

        // 4. Ciudades de Ecuador
        Schema::create('dbo.com_ciudad', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('provincia_id');
            $table->string('nombre', 100);
            $table->foreign('provincia_id')->references('id')->on('dbo.com_provincia');
        });

        // 5. Documentos de solicitud de comisión
        Schema::create('dbo.com_solicitud_documento', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('solicitud_id');
            $table->string('tipo_doc', 30);
            $table->string('alfresco_id', 100)->nullable();
            $table->string('nombre_archivo', 200)->nullable();
            $table->string('created_by', 20)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->foreign('solicitud_id')->references('id')->on('dbo.com_solicitud')->onDelete('cascade');
        });

        // 6. PDF firmado en com_informe
        Schema::table('dbo.com_informe', function (Blueprint $table) {
            $table->string('pdf_firmado_id', 100)->nullable()->after('estado');
            $table->string('pdf_firmado_nombre', 200)->nullable()->after('pdf_firmado_id');
        });

        // 7. Rol COMISIONADO EXTERNO
        DB::table('dbo.admin_rol')->insert([
            'descripcion' => 'COMISIONADO EXTERNO',
            'estado'      => true,
        ]);

        // Seed provincias
        $provincias = [
            ['id' => 1,  'nombre' => 'AZUAY'],
            ['id' => 2,  'nombre' => 'BOLÍVAR'],
            ['id' => 3,  'nombre' => 'CAÑAR'],
            ['id' => 4,  'nombre' => 'CARCHI'],
            ['id' => 5,  'nombre' => 'CHIMBORAZO'],
            ['id' => 6,  'nombre' => 'COTOPAXI'],
            ['id' => 7,  'nombre' => 'EL ORO'],
            ['id' => 8,  'nombre' => 'ESMERALDAS'],
            ['id' => 9,  'nombre' => 'GALÁPAGOS'],
            ['id' => 10, 'nombre' => 'GUAYAS'],
            ['id' => 11, 'nombre' => 'IMBABURA'],
            ['id' => 12, 'nombre' => 'LOJA'],
            ['id' => 13, 'nombre' => 'LOS RÍOS'],
            ['id' => 14, 'nombre' => 'MANABÍ'],
            ['id' => 15, 'nombre' => 'MORONA SANTIAGO'],
            ['id' => 16, 'nombre' => 'NAPO'],
            ['id' => 17, 'nombre' => 'ORELLANA'],
            ['id' => 18, 'nombre' => 'PASTAZA'],
            ['id' => 19, 'nombre' => 'PICHINCHA'],
            ['id' => 20, 'nombre' => 'SANTA ELENA'],
            ['id' => 21, 'nombre' => 'SANTO DOMINGO DE LOS TSÁCHILAS'],
            ['id' => 22, 'nombre' => 'SUCUMBÍOS'],
            ['id' => 23, 'nombre' => 'TUNGURAHUA'],
            ['id' => 24, 'nombre' => 'ZAMORA CHINCHIPE'],
        ];
        DB::table('dbo.com_provincia')->insert($provincias);

        // Seed ciudades
        $ciudades = [
            // Azuay (1)
            ['provincia_id' => 1, 'nombre' => 'CUENCA'],
            ['provincia_id' => 1, 'nombre' => 'GUALACEO'],
            ['provincia_id' => 1, 'nombre' => 'PAUTE'],
            ['provincia_id' => 1, 'nombre' => 'SÍGSIG'],
            // Bolívar (2)
            ['provincia_id' => 2, 'nombre' => 'GUARANDA'],
            ['provincia_id' => 2, 'nombre' => 'CHILLANES'],
            ['provincia_id' => 2, 'nombre' => 'SAN MIGUEL'],
            // Cañar (3)
            ['provincia_id' => 3, 'nombre' => 'AZOGUES'],
            ['provincia_id' => 3, 'nombre' => 'CAÑAR'],
            ['provincia_id' => 3, 'nombre' => 'LA TRONCAL'],
            // Carchi (4)
            ['provincia_id' => 4, 'nombre' => 'TULCÁN'],
            ['provincia_id' => 4, 'nombre' => 'BOLÍVAR'],
            ['provincia_id' => 4, 'nombre' => 'MONTÚFAR'],
            // Chimborazo (5)
            ['provincia_id' => 5, 'nombre' => 'RIOBAMBA'],
            ['provincia_id' => 5, 'nombre' => 'ALAUSÍ'],
            ['provincia_id' => 5, 'nombre' => 'GUANO'],
            ['provincia_id' => 5, 'nombre' => 'COLTA'],
            // Cotopaxi (6)
            ['provincia_id' => 6, 'nombre' => 'LATACUNGA'],
            ['provincia_id' => 6, 'nombre' => 'LA MANÁ'],
            ['provincia_id' => 6, 'nombre' => 'PUJILÍ'],
            ['provincia_id' => 6, 'nombre' => 'SAQUISILÍ'],
            // El Oro (7)
            ['provincia_id' => 7, 'nombre' => 'MACHALA'],
            ['provincia_id' => 7, 'nombre' => 'HUAQUILLAS'],
            ['provincia_id' => 7, 'nombre' => 'PASAJE'],
            ['provincia_id' => 7, 'nombre' => 'PIÑAS'],
            ['provincia_id' => 7, 'nombre' => 'SANTA ROSA'],
            // Esmeraldas (8)
            ['provincia_id' => 8, 'nombre' => 'ESMERALDAS'],
            ['provincia_id' => 8, 'nombre' => 'ATACAMES'],
            ['provincia_id' => 8, 'nombre' => 'QUININDÉ'],
            ['provincia_id' => 8, 'nombre' => 'MUISNE'],
            // Galápagos (9)
            ['provincia_id' => 9, 'nombre' => 'PUERTO AYORA'],
            ['provincia_id' => 9, 'nombre' => 'PUERTO BAQUERIZO MORENO'],
            ['provincia_id' => 9, 'nombre' => 'PUERTO VILLAMIL'],
            // Guayas (10)
            ['provincia_id' => 10, 'nombre' => 'GUAYAQUIL'],
            ['provincia_id' => 10, 'nombre' => 'DURÁN'],
            ['provincia_id' => 10, 'nombre' => 'MILAGRO'],
            ['provincia_id' => 10, 'nombre' => 'PLAYAS'],
            ['provincia_id' => 10, 'nombre' => 'SAMBORONDÓN'],
            ['provincia_id' => 10, 'nombre' => 'DAULE'],
            // Imbabura (11)
            ['provincia_id' => 11, 'nombre' => 'IBARRA'],
            ['provincia_id' => 11, 'nombre' => 'ATUNTAQUI'],
            ['provincia_id' => 11, 'nombre' => 'COTACACHI'],
            ['provincia_id' => 11, 'nombre' => 'OTAVALO'],
            // Loja (12)
            ['provincia_id' => 12, 'nombre' => 'LOJA'],
            ['provincia_id' => 12, 'nombre' => 'CATAMAYO'],
            ['provincia_id' => 12, 'nombre' => 'MACARÁ'],
            ['provincia_id' => 12, 'nombre' => 'CELICA'],
            // Los Ríos (13)
            ['provincia_id' => 13, 'nombre' => 'BABAHOYO'],
            ['provincia_id' => 13, 'nombre' => 'QUEVEDO'],
            ['provincia_id' => 13, 'nombre' => 'VENTANAS'],
            ['provincia_id' => 13, 'nombre' => 'VINCES'],
            // Manabí (14)
            ['provincia_id' => 14, 'nombre' => 'PORTOVIEJO'],
            ['provincia_id' => 14, 'nombre' => 'MANTA'],
            ['provincia_id' => 14, 'nombre' => 'BAHÍA DE CARÁQUEZ'],
            ['provincia_id' => 14, 'nombre' => 'CHONE'],
            ['provincia_id' => 14, 'nombre' => 'EL CARMEN'],
            ['provincia_id' => 14, 'nombre' => 'JIPIJAPA'],
            ['provincia_id' => 14, 'nombre' => 'PEDERNALES'],
            // Morona Santiago (15)
            ['provincia_id' => 15, 'nombre' => 'MACAS'],
            ['provincia_id' => 15, 'nombre' => 'GUALAQUIZA'],
            ['provincia_id' => 15, 'nombre' => 'MÉNDEZ'],
            // Napo (16)
            ['provincia_id' => 16, 'nombre' => 'TENA'],
            ['provincia_id' => 16, 'nombre' => 'ARCHIDONA'],
            ['provincia_id' => 16, 'nombre' => 'EL CHACO'],
            // Orellana (17)
            ['provincia_id' => 17, 'nombre' => 'FRANCISCO DE ORELLANA'],
            ['provincia_id' => 17, 'nombre' => 'LA JOYA DE LOS SACHAS'],
            ['provincia_id' => 17, 'nombre' => 'LORETO'],
            // Pastaza (18)
            ['provincia_id' => 18, 'nombre' => 'PUYO'],
            ['provincia_id' => 18, 'nombre' => 'MERA'],
            ['provincia_id' => 18, 'nombre' => 'SANTA CLARA'],
            // Pichincha (19)
            ['provincia_id' => 19, 'nombre' => 'QUITO'],
            ['provincia_id' => 19, 'nombre' => 'CAYAMBE'],
            ['provincia_id' => 19, 'nombre' => 'MEJÍA'],
            ['provincia_id' => 19, 'nombre' => 'SANGOLQUÍ'],
            ['provincia_id' => 19, 'nombre' => 'PEDRO MONCAYO'],
            // Santa Elena (20)
            ['provincia_id' => 20, 'nombre' => 'LA LIBERTAD'],
            ['provincia_id' => 20, 'nombre' => 'SALINAS'],
            ['provincia_id' => 20, 'nombre' => 'SANTA ELENA'],
            // Santo Domingo (21)
            ['provincia_id' => 21, 'nombre' => 'SANTO DOMINGO'],
            // Sucumbíos (22)
            ['provincia_id' => 22, 'nombre' => 'NUEVA LOJA'],
            ['provincia_id' => 22, 'nombre' => 'SHUSHUFINDI'],
            ['provincia_id' => 22, 'nombre' => 'LAGO AGRIO'],
            // Tungurahua (23)
            ['provincia_id' => 23, 'nombre' => 'AMBATO'],
            ['provincia_id' => 23, 'nombre' => 'BAÑOS'],
            ['provincia_id' => 23, 'nombre' => 'PELILEO'],
            ['provincia_id' => 23, 'nombre' => 'PÍLLARO'],
            // Zamora Chinchipe (24)
            ['provincia_id' => 24, 'nombre' => 'ZAMORA'],
            ['provincia_id' => 24, 'nombre' => 'EL PANGUI'],
            ['provincia_id' => 24, 'nombre' => 'NANGARITZA'],
        ];
        DB::table('dbo.com_ciudad')->insert($ciudades);
    }

    public function down(): void
    {
        Schema::dropIfExists('dbo.com_solicitud_documento');
        Schema::dropIfExists('dbo.com_ciudad');
        Schema::dropIfExists('dbo.com_provincia');

        Schema::table('dbo.com_informe', function (Blueprint $table) {
            $table->dropColumn(['pdf_firmado_id', 'pdf_firmado_nombre']);
        });

        Schema::table('dbo.com_funcionario_externo', function (Blueprint $table) {
            $table->dropColumn(['programa', 'actividad']);
        });

        Schema::table('dbo.ad_empleado', function (Blueprint $table) {
            $table->dropColumn('es_externo');
        });

        DB::table('dbo.admin_rol')->where('descripcion', 'COMISIONADO EXTERNO')->delete();
    }
};
