<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class DatosBaseInicialesService
{
    /** Carga exclusivamente el snapshot revisado; conserva filas con la misma clave. */
    public function cargar(): array
    {
        $connection = DB::connection('pgsql');
        if ($connection->getDriverName() !== 'pgsql') {
            throw new RuntimeException('La carga inicial requiere PostgreSQL.');
        }
        $path = database_path('baseline/datos-base.json');
        $manifest = json_decode(file_get_contents(database_path('baseline/manifest.json')), true, 512, JSON_THROW_ON_ERROR);
        if (! hash_equals($manifest['data_sha256'], hash_file('sha256', $path))) {
            throw new RuntimeException('El snapshot no coincide con el manifiesto revisado.');
        }
        $data = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if ($data['format'] !== 1 || count($data['tables']) !== count($manifest['counts'])) {
            throw new RuntimeException('Formato o inventario de datos base inválido.');
        }

        return $connection->transaction(function () use ($connection, $data, $manifest): array {
            $connection->select('SELECT pg_advisory_xact_lock(hashtext(?))', ['rrhh_datos_base_iniciales_v1']);
            $result = ['insertados' => 0, 'conservados' => 0, 'tablas' => 0];
            foreach ($data['tables'] as $table) {
                $name = $table['name'];
                if (! preg_match('/^(dbo|adq)\.[a-z][a-z0-9_]*$/', $name)
                    || ! array_key_exists($name, $manifest['counts'])
                    || count($table['rows']) !== $manifest['counts'][$name]) {
                    throw new RuntimeException('Tabla o cantidad de filas inesperada en el snapshot.');
                }
                foreach ($table['rows'] as $row) {
                    // Con PK no se sobrescriben valores configurados. Sin PK se
                    // compara la fila completa, incluidos los NULL.
                    $identity = $table['primary_key']
                        ? array_intersect_key($row, array_flip($table['primary_key']))
                        : $row;
                    if (! $identity) {
                        throw new RuntimeException('Una fila base no tiene identidad.');
                    }
                    $query = $connection->table($name);
                    foreach ($identity as $column => $value) {
                        $value === null ? $query->whereNull($column) : $query->where($column, $value);
                    }
                    if ($query->exists()) {
                        $result['conservados']++;
                    } else {
                        $connection->table($name)->insert($row);
                        $result['insertados']++;
                    }
                }
                // Cada secuencia se obtiene de la metadata PostgreSQL. Nunca se
                // disminuye un contador ya utilizado por la aplicación.
                $columns = $connection->select('SELECT a.attname AS name, pg_get_serial_sequence(?, a.attname) AS sequence FROM pg_attribute a WHERE a.attrelid=CAST(? AS regclass) AND a.attnum>0 AND NOT a.attisdropped', [$name, $name]);
                foreach ($columns as $column) {
                    if (! $column->sequence) {
                        continue;
                    }
                    $max = $connection->table($name)->max($column->name);
                    if ($max === null) {
                        continue;
                    }
                    $quotedSequence = $connection->getQueryGrammar()->wrapTable($column->sequence);
                    $state = $connection->selectOne('SELECT last_value, is_called FROM '.$quotedSequence);
                    $connection->select('SELECT setval(CAST(? AS regclass), ?, ?)', [
                        $column->sequence,
                        max((int) $max, (int) $state->last_value),
                        true,
                    ]);
                }
                $result['tablas']++;
            }

            return $result;
        });
    }
}
