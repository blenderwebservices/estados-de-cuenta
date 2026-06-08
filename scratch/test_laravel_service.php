<?php

use App\Models\BankStatement;
use App\Services\BankStatementParser;
use App\Services\ExcelExporter;
use Illuminate\Support\Facades\Storage;

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Let's create a test record in the database
$pdfRelativePath = 'assets/ejemplos/BBVA CH/01. 6814 TC BBVA.pdf';
$fileName = basename($pdfRelativePath);
$storagePath = 'statements/' . $fileName;

// Ensure statements directory exists in local storage
Storage::disk('local')->put($storagePath, file_get_contents($pdfRelativePath));

$statement = BankStatement::create([
    'file_name' => $fileName,
    'file_path' => $storagePath,
    'bank_type' => 'BBVA TC',
    'status' => 'pending',
]);

echo "Created statement record with ID: {$statement->id}\n";
echo "Parsing statement...\n";

$parser = app(BankStatementParser::class);
$parser->parse($statement);

$statement->refresh();
echo "Parser status: {$statement->status}\n";
echo "Error message: " . ($statement->error_message ?? 'None') . "\n";
echo "Is balanced: " . ($statement->is_balanced ? 'YES' : 'NO') . "\n";
echo "Account number: {$statement->account_number}\n";
echo "Total cargos: {$statement->total_cargos}, calculated cargos: {$statement->calculated_cargos}\n";
echo "Total abonos: {$statement->total_abonos}, calculated abonos: {$statement->calculated_abonos}\n";
echo "Lines count: " . $statement->lines()->count() . "\n";

if ($statement->status === 'completed') {
    echo "Exporting to Excel...\n";
    $exporter = app(ExcelExporter::class);
    $excelPath = $exporter->export($statement);
    
    $outputPath = __DIR__ . '/output_test.xlsx';
    copy($excelPath, $outputPath);
    unlink($excelPath);
    echo "Excel exported successfully to: {$outputPath}\n";
}
