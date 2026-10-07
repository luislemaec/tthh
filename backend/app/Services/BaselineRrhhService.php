<?php

namespace App\Services;

use PDO;
use RuntimeException;

class BaselineRrhhService
{
    /** Metadata estructural, sin consultar valores de negocio. */
    public static function inspect(PDO $pdo): array
    {
        $relations = $pdo->query("SELECT n.nspname||'.'||c.relname AS name, c.relkind FROM pg_class c JOIN pg_namespace n ON n.oid=c.relnamespace WHERE n.nspname IN ('dbo','adq','public') AND c.relkind IN ('r','p','v') AND c.relname<>'migrations' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        $result = [];
        foreach ($relations as $relation) {
            $query = $pdo->prepare('SELECT attname,format_type(atttypid,atttypmod) AS type,attnotnull FROM pg_attribute WHERE attrelid=CAST(? AS regclass) AND attnum>0 AND NOT attisdropped ORDER BY attnum');
            $query->execute([$relation['name']]);
            $columns = [];
            foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $column) {
                $type = preg_replace('/^character(\(\d+\))?$/', 'character varying$1', $column['type']);
                if ($type === 'bpchar') {
                    $type = 'character varying';
                }
                $columns[$column['attname']] = ['type' => $type, 'not_null' => (bool) $column['attnotnull']];
            }
            $query = $pdo->prepare("SELECT conname,contype,condeferrable,condeferred,confupdtype,confdeltype,(SELECT json_agg(a.attname ORDER BY k.ord) FROM unnest(c.conkey) WITH ORDINALITY k(num,ord) JOIN pg_attribute a ON a.attrelid=c.conrelid AND a.attnum=k.num) AS columns,CASE WHEN c.confrelid<>0 THEN (SELECT n.nspname||'.'||r.relname FROM pg_class r JOIN pg_namespace n ON n.oid=r.relnamespace WHERE r.oid=c.confrelid) END AS parent,(SELECT json_agg(a.attname ORDER BY k.ord) FROM unnest(c.confkey) WITH ORDINALITY k(num,ord) JOIN pg_attribute a ON a.attrelid=c.confrelid AND a.attnum=k.num) AS parent_columns FROM pg_constraint c WHERE c.conrelid=CAST(? AS regclass) AND c.contype IN ('p','u','f') ORDER BY conname");
            $query->execute([$relation['name']]);
            $constraints = [];
            foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $constraint) {
                $name = $constraint['conname'];
                unset($constraint['conname']);
                $constraint['condeferrable'] = (bool) $constraint['condeferrable'];
                $constraint['condeferred'] = (bool) $constraint['condeferred'];
                $constraint['columns'] = json_decode($constraint['columns'] ?? 'null', true, 512, JSON_THROW_ON_ERROR);
                $constraint['parent_columns'] = json_decode($constraint['parent_columns'] ?? 'null', true, 512, JSON_THROW_ON_ERROR);
                $constraints[$name] = $constraint;
            }
            $result[$relation['name']] = ['kind' => $relation['relkind'], 'columns' => $columns, 'constraints' => $constraints];
        }

        return $result;
    }

    public function validate(PDO $pdo, bool $allowOldSupervisorConstraint = false): array
    {
        $path = database_path('baseline/contrato-estructura.json');
        $manifest = json_decode(file_get_contents(database_path('baseline/manifest.json')), true, 512, JSON_THROW_ON_ERROR);
        if (! hash_equals($manifest['contract_sha256'], hash_file('sha256', $path))) {
            throw new RuntimeException('El contrato estructural no coincide con el manifiesto.');
        }
        $required = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $actual = self::inspect($pdo);
        $problems = [];
        foreach ($required as $name => $relation) {
            if (! isset($actual[$name]) || $actual[$name]['kind'] !== $relation['kind']) {
                $problems[] = 'Relación ausente o de otro tipo: '.$name;

                continue;
            }
            foreach ($relation['columns'] as $column => $definition) {
                if (($actual[$name]['columns'][$column] ?? null) !== $definition) {
                    $problems[] = 'Columna ausente o incompatible: '.$name.'.'.$column.' (esperado '.json_encode($definition).'; actual '.json_encode($actual[$name]['columns'][$column] ?? null).')';
                }
            }
            foreach ($relation['constraints'] as $constraint => $definition) {
                if ($allowOldSupervisorConstraint && $name === 'dbo.supervisor_area' && $constraint === 'uq_supervisor_area_depto_sup'
                    && ! isset($actual[$name]['constraints'][$constraint])
                    && isset($actual[$name]['constraints']['uq_supervisor_area'])) {
                    $legacy = $actual[$name]['constraints']['uq_supervisor_area'];
                    if ($legacy['columns'] === ['id_depto']) {
                        $legacy['columns'] = ['id_depto', 'id_supervisor'];
                        if ($legacy === $definition) {
                            continue;
                        }
                    }
                }
                if (($actual[$name]['constraints'][$constraint] ?? null) !== $definition) {
                    $problems[] = 'Restricción ausente o incompatible: '.$name.'.'.$constraint;
                }
            }
            if ($name === 'dbo.supervisor_area' && isset($actual[$name]['constraints']['uq_supervisor_area'])) {
                $legacy = $actual[$name]['constraints']['uq_supervisor_area'];
                if ($legacy['contype'] !== 'u' || $legacy['columns'] !== ['id_depto']) {
                    $problems[] = 'uq_supervisor_area tiene una definición inesperada; no se sustituye automáticamente.';
                } elseif (! $allowOldSupervisorConstraint) {
                    $problems[] = 'La restricción antigua uq_supervisor_area sigue limitando un supervisor por departamento. Revisar --actualizar-supervisores.';
                }
            }
        }

        return $problems;
    }
}
