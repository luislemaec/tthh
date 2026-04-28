<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("
            CREATE TABLE adq.kardex (
                id                SERIAL PRIMARY KEY,
                articulo_id       INT NOT NULL REFERENCES adq.articulo(id),
                fecha             TIMESTAMP NOT NULL,
                tipo_movimiento   VARCHAR(20) NOT NULL,
                referencia_tipo   VARCHAR(20) NOT NULL,
                referencia_id     INT NOT NULL,
                referencia_det_id INT NOT NULL,
                numero_documento  VARCHAR(50) NULL,
                cantidad_entrada  DECIMAL(12,4) DEFAULT 0,
                cantidad_salida   DECIMAL(12,4) DEFAULT 0,
                stock_antes       DECIMAL(12,4) NOT NULL,
                stock_despues     DECIMAL(12,4) NOT NULL,
                precio_antes      DECIMAL(10,5) NOT NULL,
                precio_despues    DECIMAL(10,5) NOT NULL,
                precio_movimiento DECIMAL(10,5) NOT NULL,
                subtotal          DECIMAL(12,2) NOT NULL DEFAULT 0,
                iva_valor         DECIMAL(12,2) NOT NULL DEFAULT 0,
                total_linea       DECIMAL(12,2) NOT NULL DEFAULT 0,
                usuario           VARCHAR(20) NOT NULL,
                observacion       TEXT NULL,
                created_at        TIMESTAMP DEFAULT now()
            )
        ");

        DB::statement('CREATE INDEX idx_kardex_articulo_fecha ON adq.kardex(articulo_id, fecha)');
        DB::statement('CREATE INDEX idx_kardex_ref ON adq.kardex(referencia_tipo, referencia_id)');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS adq.kardex');
    }
};
