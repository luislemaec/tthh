<?php

namespace Database\Seeders;

use App\Services\DatosBaseInicialesService;
use Illuminate\Database\Seeder;

class DatosBaseInicialesSeeder extends Seeder
{
    public function run(): void
    {
        $result = app(DatosBaseInicialesService::class)->cargar();
        $this->command?->info(sprintf('%d tablas: %d insertados, %d conservados.', $result['tablas'], $result['insertados'], $result['conservados']));
    }
}
