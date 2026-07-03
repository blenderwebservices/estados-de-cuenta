<?php

namespace Tests\Feature;

use App\Filament\Resources\BankStatements\Pages\ViewBankStatement;
use App\Filament\Resources\BankStatements\RelationManagers\LinesRelationManager;
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

    public function test_parser_respects_estado_cuenta_and_contacto_sugerido(): void
    {
        // 1. Arrange: Create seed cases
        // A generic case that applies to all bank statements (null estado_cuenta)
        Caso::create([
            'caso' => 'COMISION',
            'sugerencia' => 'Comisión genérica',
        ]);

        // A case specific to 'BBVA CH'
        Caso::create([
            'caso' => 'INTERESES',
            'sugerencia' => 'Intereses BBVA',
            'estado_cuenta' => 'BBVA CH',
            'contacto_sugerido' => 'BANCO BBVA',
        ]);

        // A case specific to 'AMEX TC' (which should NOT match when parsing BBVA CH)
        Caso::create([
            'caso' => 'RETIRO',
            'sugerencia' => 'Retiro de efectivo AMEX',
            'estado_cuenta' => 'AMEX TC',
        ]);

        $statement = BankStatement::create([
            'file_name' => 'test_statement_2.pdf',
            'file_path' => 'statements/test_statement_2.pdf',
            'bank_type' => 'BBVA CH',
            'status' => 'pending',
        ]);

        // Mock PDF file exists
        $dummyPath = storage_path('app/statements/test_statement_2.pdf');
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
                    'codigo' => 'INTERESES_TEST',
                    'etiqueta' => 'ABONO DE INTERESES',
                    'importe' => 1500.0,
                    'saldo' => 2000.0
                ],
                [
                    'fecha' => '2026-06-15',
                    'codigo' => 'RETIRO_TEST',
                    'etiqueta' => 'RETIRO DE CAJERO AUTOMATICO',
                    'importe' => -100.0,
                    'saldo' => 1900.0
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

        // 3. Assert
        $statement->refresh();
        $lines = $statement->lines()->orderBy('fecha')->get();
        $this->assertCount(3, $lines);

        // Line 1: COMISION (generic) matches
        $this->assertEquals('PAGO DE COMISION BANCARIA', $lines[0]->etiqueta);
        $this->assertEquals('COMISION', $lines[0]->casos);
        $this->assertEquals('Comisión genérica', $lines[0]->sugerencia);
        $this->assertNull($lines[0]->contacto);

        // Line 2: INTERESES (specific to BBVA CH) matches and has suggested contact
        $this->assertEquals('ABONO DE INTERESES', $lines[1]->etiqueta);
        $this->assertEquals('INTERESES', $lines[1]->casos);
        $this->assertEquals('Intereses BBVA', $lines[1]->sugerencia);
        $this->assertEquals('BANCO BBVA', $lines[1]->contacto);

        // Line 3: RETIRO (specific to AMEX TC) should NOT match since this is a BBVA CH statement
        $this->assertEquals('RETIRO DE CAJERO AUTOMATICO', $lines[2]->etiqueta);
        $this->assertNull($lines[2]->casos);
        $this->assertNull($lines[2]->sugerencia);
        $this->assertNull($lines[2]->contacto);
    }

    public function test_lines_relation_manager_aplicar_casos_action(): void
    {
        // Arrange
        $statement = BankStatement::create([
            'file_name' => 'test.pdf',
            'file_path' => 'statements/test.pdf',
            'bank_type' => 'BBVA CH',
            'status' => 'completed',
        ]);

        $line = $statement->lines()->create([
            'fecha' => '2026-06-05',
            'codigo' => 'COMISION_TEST',
            'etiqueta' => 'PAGO DE COMISION BANCARIA',
            'importe' => -500.0,
        ]);

        Caso::create([
            'caso' => 'COMISION',
            'sugerencia' => 'Comisión genérica',
        ]);

        // Act & Assert
        \Livewire\Livewire::test(LinesRelationManager::class, [
            'ownerRecord' => $statement,
            'pageClass' => ViewBankStatement::class,
        ])
        ->callTableAction('aplicarCasos');

        // Assert line updated
        $line->refresh();
        $this->assertEquals('COMISION', $line->casos);
        $this->assertEquals('Comisión genérica', $line->sugerencia);
    }

    public function test_lines_relation_manager_crear_caso_action(): void
    {
        // Arrange
        $statement = BankStatement::create([
            'file_name' => 'test.pdf',
            'file_path' => 'statements/test.pdf',
            'bank_type' => 'BBVA CH',
            'status' => 'completed',
        ]);

        $line = $statement->lines()->create([
            'fecha' => '2026-06-05',
            'codigo' => 'COMISION_TEST',
            'etiqueta' => 'PAGO DE COMISION BANCARIA',
            'importe' => -500.0,
        ]);

        // Act & Assert
        \Livewire\Livewire::test(LinesRelationManager::class, [
            'ownerRecord' => $statement,
            'pageClass' => ViewBankStatement::class,
        ])
        ->callTableAction('crearCaso', $line->getKey(), [
            'caso' => 'COMISION_NUEVA',
            'sugerencia' => 'Comisión nueva',
            'estado_cuenta' => 'BBVA CH',
            'contacto_sugerido' => null,
            'aplicar_a_linea' => true,
        ]);

        // Assert database has new case
        $this->assertDatabaseHas('casos', [
            'caso' => 'COMISION_NUEVA',
            'sugerencia' => 'Comisión nueva',
        ]);

        // Assert line updated
        $line->refresh();
        $this->assertEquals('COMISION_NUEVA', $line->casos);
        $this->assertEquals('Comisión nueva', $line->sugerencia);
    }
}
