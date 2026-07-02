<?php

namespace Tests\Feature;

use App\Models\BankStatement;
use App\Models\BankStatementLine;
use App\Services\ExcelExporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ExcelExporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_excel_exporter_populates_suggestion_and_contact(): void
    {
        // 1. Arrange: Create statement and a line
        $statement = BankStatement::create([
            'file_name' => 'statement.pdf',
            'file_path' => 'statements/statement.pdf',
            'bank_type' => 'BBVA CH',
            'status' => 'completed',
        ]);

        BankStatementLine::create([
            'bank_statement_id' => $statement->id,
            'fecha' => '2026-06-05',
            'codigo' => 'TX_01',
            'etiqueta' => 'PAGO DE COMISION BANCARIA',
            'importe' => -500.0,
            'saldo' => 500.0,
            'casos' => 'COMISION',
            'sugerencia' => 'Comisión bancaria - Sin factura',
            'contacto' => 'JUAN PEREZ',
        ]);

        // 2. Act: Export to Excel
        $exporter = new ExcelExporter();
        $filePath = $exporter->export($statement);

        // 3. Assert: Read Excel file and verify cell values
        $this->assertFileExists($filePath);

        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getSheetByName('Movimientos');
        $this->assertNotNull($sheet);

        // Check headers
        $this->assertEquals('Fecha', $sheet->getCell('A1')->getValue());
        $this->assertEquals('Etiqueta', $sheet->getCell('B1')->getValue());
        $this->assertEquals('Contacto', $sheet->getCell('C1')->getValue());
        $this->assertEquals('Importe', $sheet->getCell('D1')->getValue());
        $this->assertEquals('Concepto / Etiqueta', $sheet->getCell('E1')->getValue());

        // Check values in Row 2
        // Column B should contain suggestion
        $this->assertEquals('Comisión bancaria - Sin factura', $sheet->getCell('B2')->getValue());
        // Column C should contain contact
        $this->assertEquals('JUAN PEREZ', $sheet->getCell('C2')->getValue());
        // Column E should contain raw label
        $this->assertEquals('PAGO DE COMISION BANCARIA', $sheet->getCell('E2')->getValue());

        // Clean up temp file
        @unlink($filePath);
    }
}
