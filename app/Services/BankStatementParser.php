<?php

namespace App\Services;

use App\Models\BankStatement;
use App\Models\BankStatementLine;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class BankStatementParser
{
    public function parse(BankStatement $statement): void
    {
        $statement->update(['status' => 'processing']);

        // Find the absolute path to the uploaded PDF
        $filePath = $statement->file_path;
        $pdfPath = null;

        // Try public disk first, then local, then direct app paths
        if (Storage::disk('public')->exists($filePath)) {
            $pdfPath = Storage::disk('public')->path($filePath);
        } elseif (Storage::disk('local')->exists($filePath)) {
            $pdfPath = Storage::disk('local')->path($filePath);
        } else {
            $pdfPath = storage_path('app/' . $filePath);
            if (!file_exists($pdfPath)) {
                $pdfPath = storage_path('app/private/' . $filePath);
            }
        }

        if (!file_exists($pdfPath)) {
            $statement->update([
                'status' => 'failed',
                'error_message' => 'PDF file not found in storage: ' . $filePath
            ]);
            return;
        }

        $scriptPath = base_path('database/scripts/parse_statement.py');

        // Execute using Laravel's Process facade
        $result = Process::run([
            config('services.python.path'),
            $scriptPath,
            $pdfPath,
            $statement->bank_type
        ]);

        if (!$result->successful()) {
            $statement->update([
                'status' => 'failed',
                'error_message' => 'Python script failed: ' . $result->errorOutput()
            ]);
            return;
        }

        $output = json_decode($result->output(), true);

        if (!$output || (isset($output['success']) && !$output['success'])) {
            $error = $output['error'] ?? 'Invalid JSON returned from Python script';
            $statement->update([
                'status' => 'failed',
                'error_message' => $error
            ]);
            return;
        }

        $meta = $output['metadata'];
        $transactions = $output['transactions'];

        // Compute balances and checks
        $sumCargos = 0.0;
        $sumAbonos = 0.0;
        foreach ($transactions as $tx) {
            if ($tx['importe'] < 0) {
                $sumCargos += abs($tx['importe']);
            } else {
                $sumAbonos += $tx['importe'];
            }
        }

        $diffCargos = abs($sumCargos - $meta['total_cargos']);
        $diffAbonos = abs($sumAbonos - $meta['total_abonos']);

        // Check bank class for math balance
        // TC = Credit Card (AMEX TC, BBVA TC)
        $isCreditCard = str_contains($statement->bank_type, 'TC');
        if ($isCreditCard) {
            // Debt balance formula: Saldo Final = Saldo Inicial + sum_cargos - sum_abonos
            // But we stored Cargos as negative values in tx.
            // So sum_cargos is positive absolute value here.
            $calcFinalBalance = $meta['saldo_inicial'] + $sumCargos - $sumAbonos;
        } else {
            // Checking account: Saldo Final = Saldo Inicial + Abonos - Cargos (cargos are negative in DB)
            $calcFinalBalance = $meta['saldo_inicial'] + $sumAbonos - $sumCargos;
        }

        $diffBalance = abs($calcFinalBalance - $meta['saldo_final']);
        $isBalanced = ($diffBalance < 0.1) && ($diffCargos < 0.05) && ($diffAbonos < 0.05);

        // Save metadata to database
        $statement->update([
            'account_number' => $meta['account'],
            'clabe' => $meta['clabe'] ?? null,
            'period_start' => $meta['period_start'],
            'period_end' => $meta['period_end'],
            'saldo_inicial' => $meta['saldo_inicial'],
            'saldo_final' => $meta['saldo_final'],
            'total_cargos' => $meta['total_cargos'],
            'total_abonos' => $meta['total_abonos'],
            'count_cargos' => $meta['count_cargos'],
            'count_abonos' => $meta['count_abonos'],
            'calculated_cargos' => $sumCargos,
            'calculated_abonos' => $sumAbonos,
            'difference_cargos' => $diffCargos,
            'difference_abonos' => $diffAbonos,
            'is_balanced' => $isBalanced,
            'status' => 'completed',
            'error_message' => null
        ]);

        // Load cases and contacts for matching
        $casos = \App\Models\Caso::where(function ($query) use ($statement) {
            $query->whereNull('estado_cuenta')
                  ->orWhere('estado_cuenta', '')
                  ->orWhere('estado_cuenta', $statement->bank_type);
        })->get();
        $contactos = \App\Models\Contacto::all();

        // Delete existing lines if any
        $statement->lines()->delete();

        // Create transaction lines
        foreach ($transactions as $tx) {
            $etiqueta = $tx['etiqueta'];

            // Match cases
            $matchedCaso = null;
            $matchedSugerencia = null;
            $matchedContacto = null;
            foreach ($casos as $casoItem) {
                if (stripos($etiqueta, $casoItem->caso) !== false) {
                    $matchedCaso = $casoItem->caso;
                    $matchedSugerencia = $casoItem->sugerencia;
                    if ($casoItem->contacto_sugerido) {
                        $matchedContacto = $casoItem->contacto_sugerido;
                    }
                    break;
                }
            }

            // Match contacts
            if (!$matchedContacto) {
                foreach ($contactos as $contactoItem) {
                    if (stripos($etiqueta, $contactoItem->nombre) !== false) {
                        $matchedContacto = $contactoItem->nombre;
                        break;
                    }
                }
            }

            $statement->lines()->create([
                'fecha' => $tx['fecha'],
                'codigo' => $tx['codigo'] ?? null,
                'etiqueta' => $etiqueta,
                'importe' => $tx['importe'],
                'saldo' => $tx['saldo'] ?? null,
                'casos' => $matchedCaso,
                'sugerencia' => $matchedSugerencia,
                'contacto' => $matchedContacto,
            ]);
        }
    }
}
