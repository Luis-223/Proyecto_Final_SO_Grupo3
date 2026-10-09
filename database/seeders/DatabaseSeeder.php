<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Crea las cuentas de demostración (Administrador y Observador).
 * Las contraseñas se leen del .env, que NUNCA se sube a GitHub.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => env('SEED_ADMIN_EMAIL', 'admin@sysmonitor.local')],
            [
                'name' => 'Administrador',
                'rol' => User::ROL_ADMINISTRADOR,
                'password' => env('SEED_ADMIN_PASSWORD') ?: throw new \RuntimeException('Defina SEED_ADMIN_PASSWORD en .env'),
            ]
        );

        User::updateOrCreate(
            ['email' => env('SEED_OBSERVADOR_EMAIL', 'observador@sysmonitor.local')],
            [
                'name' => 'Observador',
                'rol' => User::ROL_OBSERVADOR,
                'password' => env('SEED_OBSERVADOR_PASSWORD') ?: throw new \RuntimeException('Defina SEED_OBSERVADOR_PASSWORD en .env'),
            ]
        );
    }
}
