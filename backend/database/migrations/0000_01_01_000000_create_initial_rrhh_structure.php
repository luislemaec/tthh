<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    protected $connection = 'pgsql';

    public function up(): void
    {
        $connection = DB::connection($this->connection);
        if ($connection->getDriverName() !== 'pgsql') {
            throw new RuntimeException('La estructura inicial requiere PostgreSQL.');
        }
        $existing = $connection->selectOne("SELECT count(*) AS total FROM pg_class c JOIN pg_namespace n ON n.oid=c.relnamespace WHERE n.nspname NOT LIKE 'pg_%' AND n.nspname <> 'information_schema' AND c.relkind IN ('r','p','v','m') AND NOT (n.nspname='dbo' AND c.relname='migrations')");
        if ((int) $existing->total !== 0) {
            throw new RuntimeException('La estructura inicial solo puede instalarse en una BD nueva. No ejecutar sobre BDD_RRHH existente.');
        }
        $path = database_path('baseline/estructura-inicial.sql');
        $manifest = json_decode(file_get_contents(database_path('baseline/manifest.json')), true, 512, JSON_THROW_ON_ERROR);
        if (! hash_equals($manifest['schema_sha256'], hash_file('sha256', $path))) {
            throw new RuntimeException('El esquema no coincide con el manifiesto revisado.');
        }
        $searchPath = $connection->selectOne('SHOW search_path')->search_path;
        $checkBodies = $connection->selectOne('SHOW check_function_bodies')->check_function_bodies;
        // En caso de error, Laravel revierte la transacción y sus SET. No
        // consultar una transacción abortada, porque ocultaría el error original.
        $connection->unprepared(file_get_contents($path));
        $connection->select('SELECT set_config(?, ?, false)', ['search_path', $searchPath]);
        $connection->select('SELECT set_config(?, ?, false)', ['check_function_bodies', $checkBodies]);
    }

    public function down(): void
    {
        throw new RuntimeException('No hay reversión destructiva de la estructura inicial. Recuperar mediante el respaldo revisado.');
    }
};
