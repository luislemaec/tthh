<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // accion VARCHAR(20) — ya insuficiente hoy: 'IMPORTACION_DISTRIBUTIVO' (24),
        // 'REGISTRAR_MANTENIMIENTO' (23) y 'REGISTRAR_MANTENIMIENTO_EXTERNO' (31) superan el
        // límite. AuditoriaService::log() envuelve el insert en try/catch y solo escribe al log
        // de Laravel (Log::error), así que estas 3 acciones venían fallando EN SILENCIO desde
        // que se instrumentaron — ningún llamado a esas 3 dejó jamás una fila real en
        // nom_auditoria_log, sin que nadie lo notara (spec 12 §11.1). Se amplía a VARCHAR(40),
        // con margen para nombres de acción razonablemente descriptivos sin volver a repetir el
        // problema.
        DB::statement('ALTER TABLE dbo.nom_auditoria_log ALTER COLUMN accion TYPE VARCHAR(40)');

        // Append-only real (spec 12 §11.6) — hasta ahora nada impedía un UPDATE/DELETE directo
        // por SQL sobre esta tabla (la única protección era "nadie en el código llama a eso").
        // Un trigger a nivel de Postgres lo bloquea sin importar qué conexión/rol de BD lo
        // intente (salvo que alguien deshabilite el trigger explícitamente con
        // ALTER TABLE ... DISABLE TRIGGER, una acción deliberada y named, no un accidente).
        DB::statement("
            CREATE OR REPLACE FUNCTION dbo.nom_auditoria_log_bloquear_cambio()
            RETURNS TRIGGER AS \$\$
            BEGIN
                RAISE EXCEPTION 'dbo.nom_auditoria_log es append-only: % no permitido (id=%)', TG_OP, OLD.id;
                RETURN NULL;
            END;
            \$\$ LANGUAGE plpgsql;
        ");

        DB::statement("
            CREATE TRIGGER trg_nom_auditoria_log_bloquear_update
            BEFORE UPDATE ON dbo.nom_auditoria_log
            FOR EACH ROW EXECUTE FUNCTION dbo.nom_auditoria_log_bloquear_cambio();
        ");

        DB::statement("
            CREATE TRIGGER trg_nom_auditoria_log_bloquear_delete
            BEFORE DELETE ON dbo.nom_auditoria_log
            FOR EACH ROW EXECUTE FUNCTION dbo.nom_auditoria_log_bloquear_cambio();
        ");
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS trg_nom_auditoria_log_bloquear_update ON dbo.nom_auditoria_log');
        DB::statement('DROP TRIGGER IF EXISTS trg_nom_auditoria_log_bloquear_delete ON dbo.nom_auditoria_log');
        DB::statement('DROP FUNCTION IF EXISTS dbo.nom_auditoria_log_bloquear_cambio()');
        DB::statement('ALTER TABLE dbo.nom_auditoria_log ALTER COLUMN accion TYPE VARCHAR(20)');
    }
};
