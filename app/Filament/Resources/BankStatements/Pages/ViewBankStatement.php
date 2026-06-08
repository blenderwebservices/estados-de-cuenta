<?php

namespace App\Filament\Resources\BankStatements\Pages;

use App\Filament\Resources\BankStatements\BankStatementResource;
use Filament\Resources\Pages\ViewRecord;

class ViewBankStatement extends ViewRecord
{
    protected static string $resource = BankStatementResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Resources\BankStatements\Widgets\BankStatementStats::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
