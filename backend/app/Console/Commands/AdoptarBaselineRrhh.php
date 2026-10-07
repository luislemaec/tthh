<?php

namespace App\Console\Commands;

use App\Services\BaselineRrhhService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AdoptarBaselineRrhh extends Command
{
    protected $signature = 'rrhh:adoptar-baseline {--registrar : Registrar las dos migraciones iniciales tras validar} {--actualizar-supervisores : Sustituir el UNIQUE antiguo de supervisores, solo junto con --registrar}';

    protected $description = 'Comprueba una BD existente antes de adoptar el historial consolidado de RRHH';

    public function handle(): int
    {
        $connection = DB::connection('pgsql');
        $repository = app('migration.repository');
        $repository->setSource('pgsql');
        if ($connection->getDriverName() !== 'pgsql' || ! $repository->repositoryExists()) {
            $this->error('Se requiere PostgreSQL y un historial existente en la conexión pgsql.');

            return self::FAILURE;
        }
        $history = json_decode(file_get_contents(database_path('baseline/historial-consolidado.json')), true, 512, JSON_THROW_ON_ERROR);

        return $connection->transaction(function () use ($connection, $repository, $history): int {
            $connection->select('SELECT pg_advisory_xact_lock(hashtext(?))', ['rrhh_adoptar_baseline']);
            $ran = $repository->getRan();
            $already = ! array_diff($history['baseline_migrations'], $ran);
            $requiredHistory = array_filter($history['retired_migrations'], fn ($migration) => $migration['required_history'] ?? true);
            $missing = $already ? [] : array_values(array_diff(array_column($requiredHistory, 'name'), $ran));
            $problems = app(BaselineRrhhService::class)->validate($connection->getPdo(), (bool) $this->option('actualizar-supervisores'));
            foreach ($missing as $name) {
                $this->error('Migración histórica sin registrar: '.$name);
            }
            foreach ($problems as $problem) {
                $this->error($problem);
            }
            if ($missing || $problems) {
                $this->error('Adopción bloqueada. Verificar el historial y completar cambios con la versión anterior; no insertar registros para ocultar pendientes.');

                return self::FAILURE;
            }
            if ($already && ! $this->option('actualizar-supervisores')) {
                $this->info('La base inicial ya está registrada; no se cambió el historial.');

                return self::SUCCESS;
            }
            if (! $this->option('registrar')) {
                $this->info('Verificación correcta. Sin cambios. Usar --registrar para adoptar; conservar --actualizar-supervisores si se requiere ese ajuste.');

                return self::SUCCESS;
            }
            if ($this->option('actualizar-supervisores')) {
                $connection->statement("SET LOCAL lock_timeout = '10s'");
                $connection->statement("SET LOCAL statement_timeout = '120s'");
                $connection->statement('ALTER TABLE dbo.supervisor_area DROP CONSTRAINT IF EXISTS uq_supervisor_area');
                $exists = $connection->selectOne("SELECT EXISTS(SELECT 1 FROM pg_constraint WHERE conrelid='dbo.supervisor_area'::regclass AND conname='uq_supervisor_area_depto_sup') AS present");
                if (! $exists->present) {
                    $connection->statement('ALTER TABLE dbo.supervisor_area ADD CONSTRAINT uq_supervisor_area_depto_sup UNIQUE (id_depto,id_supervisor)');
                }
            }
            $batch = $repository->getNextBatchNumber();
            foreach ($history['baseline_migrations'] as $name) {
                if (! in_array($name, $ran, true)) {
                    $repository->log($name, $batch);
                }
            }
            $this->info('Base inicial registrada. No se recrearon tablas ni se cargaron datos. Historial conservado'.($this->option('actualizar-supervisores') ? '; restricción de supervisores actualizada.' : '.'));

            return self::SUCCESS;
        });
    }
}
