<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::updateOrCreate([
            'email' => 'admin@admin.com'
        ], [
            'name' => 'Administrador',
            'password' => bcrypt('admin123456'),
        ]);

        \App\Models\Caso::updateOrCreate([
            'caso' => 'COMISION',
        ], [
            'sugerencia' => 'Comisión bancaria - Sin factura',
        ]);

        \App\Models\Caso::updateOrCreate([
            'caso' => 'INTERESES',
        ], [
            'sugerencia' => 'Intereses ganados - Sin factura',
        ]);

        \App\Models\Caso::updateOrCreate([
            'caso' => 'IVA',
        ], [
            'sugerencia' => 'Impuesto al Valor Agregado',
        ]);

        \App\Models\Contacto::updateOrCreate([
            'nombre' => 'JUAN PEREZ',
        ]);

        \App\Models\Contacto::updateOrCreate([
            'nombre' => 'COMERCIALIZADORA',
        ]);
    }
}
