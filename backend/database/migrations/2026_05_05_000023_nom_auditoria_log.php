<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            CREATE TABLE IF NOT EXISTS dbo.nom_auditoria_log (
                id               BIGSERIAL PRIMARY KEY,
                tabla            VARCHAR(60)  NOT NULL,
                registro_id      INTEGER      NOT NULL,
                accion           VARCHAR(20)  NOT NULL,
                datos_anteriores JSONB        NULL,
                datos_nuevos     JSONB        NULL,
                usuario_id       VARCHAR(20)  NOT NULL,
                nombre_usuario   VARCHAR(150) NOT NULL,
                ip_origen        VARCHAR(45)  NULL,
                descripcion      VARCHAR(200) NULL,
                created_at       TIMESTAMP    NOT NULL DEFAULT NOW()
            )
        ");

        DB::statement('CREATE INDEX IF NOT EXISTS idx_nom_audit_tabla_reg ON dbo.nom_auditoria_log (tabla, registro_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_nom_audit_usuario   ON dbo.nom_auditoria_log (usuario_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_nom_audit_fecha     ON dbo.nom_auditoria_log (created_at)');
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS dbo.nom_auditoria_log');
    }
};
