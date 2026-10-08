<?php

namespace Database\Seeders;

use App\Models\AdminRol;
use App\Models\AdminUsuarioRol;
use App\Models\Departamento;
use App\Models\Empleado;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class SuperAdminInicialSeeder extends Seeder
{
    public function run(): void
    {
        DB::connection('pgsql')->transaction(function (): void {
            $departamento = Departamento::find(62);
            $rol = AdminRol::find(1);
            if (! $departamento || $departamento->estado !== 'ACTIVO'
                || ! $rol || ! $rol->estado || $rol->descripcion !== 'ADMINISTRADOR') {
                throw new RuntimeException('Cargar primero los catálogos: departamento 62 ACTIVO y rol 1 ADMINISTRADOR activo.');
            }

            // Reutiliza el mismo bloqueo que todas las altas de empleados.
            // Serializa también ejecuciones simultáneas del seeder.
            $siguienteId = Empleado::generarSiguienteId();
            $empleado = Empleado::where('identificacion', '0123467890')->first();
            if (! $empleado) {
                $password = config('bootstrap_admin.password');
                if (! is_string($password) || strlen($password) < 8) {
                    throw new RuntimeException('Configurar BOOTSTRAP_ADMIN_PASSWORD en el archivo privado del ambiente (mínimo 8 caracteres).');
                }
                $empleado = new Empleado;
                $empleado->forceFill([
                    'id_emp' => $siguienteId,
                    'identificacion' => '0123467890',
                    'nombre_emp' => 'Admin',
                    'apellido_emp' => 'Admin',
                    'id_depto' => 62,
                    'estado' => 'ACTIVO',
                    'password' => Hash::driver('bcrypt')->make($password),
                ])->save();
            } elseif ($empleado->nombre_emp !== 'Admin' || $empleado->apellido_emp !== 'Admin'
                || (int) $empleado->id_depto !== 62 || $empleado->estado !== 'ACTIVO') {
                throw new RuntimeException('La cédula inicial pertenece a un empleado con datos diferentes. Revisar manualmente; no se modificó la cuenta.');
            }

            // El modelo existente representa la PK compuesta; firstOrCreate
            // inserta solo la asignación ausente, sin actualizar esa PK.
            AdminUsuarioRol::firstOrCreate([
                'id_emp' => $empleado->id_emp,
                'identificacion' => '0123467890',
                'id_rol' => 1,
            ]);
        });
        $this->command?->info('Cuenta inicial y rol verificados. Las contraseñas existentes se conservan.');
    }
}
