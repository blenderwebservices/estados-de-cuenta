<?php

namespace Database\Seeders;

use App\Models\Contacto;
use Illuminate\Database\Seeder;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ContactoIndividuosSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $filePath = base_path('assets/contactos-individuos.xlsx');
        
        if (!file_exists($filePath)) {
            $this->command->warn("El archivo de excel no existe en: {$filePath}");
            return;
        }

        // We do NOT truncate here, because we want to keep the contacts inserted by ContactoSeeder.

        $this->command->info("Cargando y parseando el archivo de excel de individuos...");
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestRow();

        $this->command->info("Procesando {$highestRow} filas de contactos individuales...");

        $contacts = [];
        for ($row = 2; $row <= $highestRow; $row++) {
            $idExterno = $sheet->getCell('A' . $row)->getValue();
            $email = $sheet->getCell('B' . $row)->getValue();
            $nombre = $sheet->getCell('C' . $row)->getValue();
            $telefono = $sheet->getCell('D' . $row)->getValue();

            // Skip if name is completely empty
            if (empty($nombre) || empty(trim($nombre))) {
                continue;
            }

            // Convert string values and clean them up
            $idExterno = $idExterno !== null ? trim((string)$idExterno) : null;
            $email = $email !== null ? trim((string)$email) : null;
            $nombre = trim((string)$nombre);
            $telefono = $telefono !== null ? trim((string)$telefono) : null;

            $contacts[] = [
                'id_externo' => $idExterno,
                'email' => $email,
                'nombre' => $nombre,
                'telefono' => $telefono,
                'esempresa' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        // Chunk insert for performance
        foreach (array_chunk($contacts, 500) as $chunk) {
            Contacto::insert($chunk);
        }

        $this->command->info("Se han insertado " . count($contacts) . " contactos individuales correctamente.");
    }
}
