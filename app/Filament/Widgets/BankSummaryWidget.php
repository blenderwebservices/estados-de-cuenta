<?php

namespace App\Filament\Widgets;

use App\Models\BankStatement;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BankSummaryWidget extends BaseWidget
{
    protected ?string $pollingInterval = '5s';

    protected function getStats(): array
    {
        // Get all unique bank types present in database
        $bankTypes = BankStatement::where('status', 'completed')
            ->select('bank_type')
            ->distinct()
            ->pluck('bank_type');

        $stats = [];

        foreach ($bankTypes as $bankType) {
            $statements = BankStatement::where('bank_type', $bankType)
                ->where('status', 'completed')
                ->get();

            $count = $statements->count();
            if ($count === 0) {
                continue;
            }

            $totalCargos = $statements->sum('calculated_cargos');
            $totalAbonos = $statements->sum('calculated_abonos');

            // Saldo is the final balance of the most recent statement by period_end date
            $latest = BankStatement::where('bank_type', $bankType)
                ->where('status', 'completed')
                ->orderByDesc('period_end')
                ->first();
            $saldo = $latest ? $latest->saldo_final : 0.00;

            // Check if all statements of this type are mathematically balanced
            $allBalanced = !BankStatement::where('bank_type', $bankType)
                ->where('is_balanced', false)
                ->exists();

            $stats[] = Stat::make($bankType, '$' . number_format($saldo, 2))
                ->description("{$count} PDF(s) | Cargos: $" . number_format($totalCargos, 2) . " | Abonos: $" . number_format($totalAbonos, 2))
                ->descriptionIcon($allBalanced ? 'heroicon-m-check-circle' : 'heroicon-m-x-circle')
                ->color($allBalanced ? 'success' : 'danger');
        }

        // If no bank statements exist, show an empty state card
        if (empty($stats)) {
            return [
                Stat::make('Sin Datos', '$0.00')
                    ->description('Carga un estado de cuenta para ver las estadísticas de control.')
                    ->color('gray'),
            ];
        }

        return $stats;
    }
}
