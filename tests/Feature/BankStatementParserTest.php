<?php

namespace Tests\Feature;

use App\Models\BankStatement;
use App\Models\Caso;
use App\Models\Contacto;
use App\Services\BankStatementParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class BankStatementParserTest extends TestCase
{
    use RefreshDatabase;

    public function test_parser_matches_cases_and_contacts_correctly(): void
    {
        // 1. Arrange: Create seed cases and contacts
        Caso::create([
            'caso' => 'COMISION',
            'sugerencia' => 'Comisión bancaria - Sin factura',
        ]);

        Contacto::create([
            'nombre' => 'JUAN PEREZ',
        ]);

        $statement = BankStatement::create([
            'file_name' => 'test_statement.pdf',
            'file_path' => 'statements/test_statement.pdf',
            'bank_type' => 'BBVA CH',
            'status' => 'pending',
        ]);

        // Mock PDF file exists
        // (Just dummy check in storage_path, we will mock python script output anyway)
        $dummyPath = storage_path('app/statements/test_statement.pdf');
        @mkdir(dirname($dummyPath), 0777, true);
        file_put_contents($dummyPath, 'dummy pdf content');

        // Fake Process run
        $mockJson = json_encode([
            'metadata' => [
                'account' => '12345678',
                'clabe' => '012345678901234567',
                'period_start' => '2026-06-01',
                'period_end' => '2026-06-30',
                'saldo_inicial' => 1000.0,
                'saldo_final' => 2000.0,
                'total_cargos' => 500.0,
                'total_abonos' => 1500.0,
                'count_cargos' => 1,
                'count_abonos' => 1
            ],
            'transactions' => [
                [
                    'fecha' => '2026-06-05',
                    'codigo' => 'COMISION_TEST',
                    'etiqueta' => 'PAGO DE COMISION BANCARIA',
                    'importe' => -500.0,
                    'saldo' => 500.0
                ],
                [
                    'fecha' => '2026-06-10',
                    'codigo' => 'PAGO_TEST',
                    'etiqueta' => 'TRANSFERENCIA DE JUAN PEREZ',
                    'importe' => 1500.0,
                    'saldo' => 2000.0
                ]
            ]
        ]);

        Process::fake([
            '*' => Process::result($mockJson, 0),
        ]);

        // 2. Act: Parse statement
        $parser = new BankStatementParser();
        $parser->parse($statement);

        // Cleanup dummy file
        @unlink($dummyPath);

        // 3. Assert: Verify statement status
        $statement->refresh();
        $this->assertEquals('completed', $statement->status);
        $this->assertTrue($statement->is_balanced);

        // Verify transactions matching
        $lines = $statement->lines()->orderBy('fecha')->get();
        $this->assertCount(2, $lines);

        // Line 1: COMISION match
        $this->assertEquals('PAGO DE COMISION BANCARIA', $lines[0]->etiqueta);
        $this->assertEquals('COMISION', $lines[0]->casos);
        $this->assertEquals('Comisión bancaria - Sin factura', $lines[0]->sugerencia);
        $this->assertNull($lines[0]->contacto);

        // Line 2: JUAN PEREZ match
        $this->assertEquals('TRANSFERENCIA DE JUAN PEREZ', $lines[1]->etiqueta);
        $this->assertNull($lines[1]->casos);
        $this->assertNull($lines[1]->sugerencia);
        $this->assertEquals('JUAN PEREZ', $lines[1]->contacto);
    }
}
