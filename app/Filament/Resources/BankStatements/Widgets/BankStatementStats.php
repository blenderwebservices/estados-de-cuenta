<?php

namespace App\Filament\Resources\BankStatements\Widgets;

use App\Models\BankStatement;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BankStatementStats extends BaseWidget
{
    public ?BankStatement $record = null;

    protected function getStats(): array
    {
        if (!$this->record) {
            return [];
        }

        $isBalanced = $this->record->is_balanced;

        return [
            Stat::make('Saldo Inicial', '$' . number_format($this->record->saldo_inicial, 2))
                ->description('Balance al inicio del periodo'),

            Stat::make('Saldo Final', '$' . number_format($this->record->saldo_final, 2))
                ->description('Balance al corte del periodo')
                ->color($isBalanced ? 'success' : 'danger'),

            Stat::make('Cargos (PDF vs Calc)', '$' . number_format($this->record->total_cargos, 2) . ' / $' . number_format($this->record->calculated_cargos, 2))
                ->description($this->record->difference_cargos < 0.05 ? 'Cargos Conciliados' : 'Diferencia: $' . number_format($this->record->difference_cargos, 2))
                ->color($this->record->difference_cargos < 0.05 ? 'success' : 'danger'),

            Stat::make('Abonos (PDF vs Calc)', '$' . number_format($this->record->total_abonos, 2) . ' / $' . number_format($this->record->calculated_abonos, 2))
                ->description($this->record->difference_abonos < 0.05 ? 'Abonos Conciliados' : 'Diferencia: $' . number_format($this->record->difference_abonos, 2))
                ->color($this->record->difference_abonos < 0.05 ? 'success' : 'danger'),
        ];
    }
}
