<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Proveedores
        Schema::connection('pgsql')->create('adq.proveedor', function (Blueprint $table) {
            $table->id();
            $table->string('ruc', 20)->unique();
            $table->string('nombre', 200);
            $table->string('direccion', 300)->nullable();
            $table->string('contacto', 100)->nullable();
            $table->string('email', 100)->nullable();
            $table->string('telefono', 20)->nullable();
            $table->string('estado', 10)->default('ACTIVO'); // ACTIVO | INACTIVO
            $table->timestamps();
        });

        // Catálogo de productos por proveedor
        Schema::connection('pgsql')->create('adq.proveedor_catalogo', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('proveedor_id');
            $table->string('descripcion', 300);
            $table->string('unidad_medida', 50)->nullable();
            $table->decimal('precio_referencial', 10, 2)->default(0);
            $table->timestamps();

            $table->foreign('proveedor_id')->references('id')->on('adq.proveedor')->onDelete('cascade');
        });

        // Artículos del inventario
        Schema::connection('pgsql')->create('adq.articulo', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 30)->unique();
            $table->string('nombre', 200);
            $table->string('descripcion', 500)->nullable();
            $table->string('unidad_medida', 50)->nullable();
            $table->string('categoria', 100)->nullable();
            $table->decimal('stock_actual', 12, 2)->default(0);
            $table->decimal('stock_maximo_historico', 12, 2)->default(0);
            $table->string('estado', 10)->default('ACTIVO');
            $table->timestamps();
        });

        // Configuración del módulo
        Schema::connection('pgsql')->create('adq.configuracion', function (Blueprint $table) {
            $table->string('concepto', 50)->primary();
            $table->string('valor', 200);
            $table->string('descripcion', 300)->nullable();
            $table->timestamps();
        });

        // Valor inicial: porcentaje de stock mínimo = 20%
        DB::table('adq.configuracion')->insert([
            'concepto'    => 'porcentaje_stock_minimo',
            'valor'       => '20',
            'descripcion' => 'Porcentaje mínimo de stock. Alerta cuando stock_actual <= stock_maximo_historico × (valor/100)',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        // Órdenes de compra - cabecera
        Schema::connection('pgsql')->create('adq.orden_compra', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('proveedor_id');
            $table->date('fecha');
            $table->string('estado', 20)->default('BORRADOR'); // BORRADOR | ENVIADA | RECIBIDA
            $table->text('observacion')->nullable();
            $table->string('usuario_registro', 20);
            $table->string('usuario_recepcion', 20)->nullable();
            $table->timestamp('fecha_recepcion')->nullable();
            $table->timestamps();

            $table->foreign('proveedor_id')->references('id')->on('adq.proveedor');
        });

        // Órdenes de compra - detalle
        Schema::connection('pgsql')->create('adq.orden_compra_det', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('orden_id');
            $table->unsignedBigInteger('articulo_id');
            $table->decimal('cantidad', 12, 2);
            $table->decimal('precio_unitario', 10, 2)->default(0);
            $table->timestamps();

            $table->foreign('orden_id')->references('id')->on('adq.orden_compra')->onDelete('cascade');
            $table->foreign('articulo_id')->references('id')->on('adq.articulo');
        });

        // Solicitudes de materiales - cabecera
        Schema::connection('pgsql')->create('adq.solicitud_material', function (Blueprint $table) {
            $table->id();
            $table->string('id_emp', 10);
            $table->integer('id_depto');
            $table->date('fecha');
            $table->string('justificacion', 500)->nullable();
            // PENDIENTE → APROBADO (supervisor) → DESPACHADO / DESPACHADO PARCIAL / NEGADO (bienes)
            $table->string('estado', 30)->default('PENDIENTE');
            $table->string('usuario_aprobacion', 20)->nullable();
            $table->timestamp('fecha_aprobacion')->nullable();
            $table->string('usuario_despacho', 20)->nullable();
            $table->timestamp('fecha_despacho')->nullable();
            $table->text('observacion_despacho')->nullable();
            $table->timestamps();

            $table->foreign('id_emp')->references('id_emp')->on('dbo.ad_empleado');
        });

        // Solicitudes de materiales - detalle
        Schema::connection('pgsql')->create('adq.solicitud_material_det', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('solicitud_id');
            $table->unsignedBigInteger('articulo_id');
            $table->decimal('cantidad_solicitada', 12, 2);
            $table->decimal('cantidad_autorizada', 12, 2)->nullable(); // Bienes autoriza
            $table->timestamps();

            $table->foreign('solicitud_id')->references('id')->on('adq.solicitud_material')->onDelete('cascade');
            $table->foreign('articulo_id')->references('id')->on('adq.articulo');
        });
    }

    public function down(): void
    {
        Schema::connection('pgsql')->dropIfExists('adq.solicitud_material_det');
        Schema::connection('pgsql')->dropIfExists('adq.solicitud_material');
        Schema::connection('pgsql')->dropIfExists('adq.orden_compra_det');
        Schema::connection('pgsql')->dropIfExists('adq.orden_compra');
        Schema::connection('pgsql')->dropIfExists('adq.configuracion');
        Schema::connection('pgsql')->dropIfExists('adq.articulo');
        Schema::connection('pgsql')->dropIfExists('adq.proveedor_catalogo');
        Schema::connection('pgsql')->dropIfExists('adq.proveedor');
    }
};
