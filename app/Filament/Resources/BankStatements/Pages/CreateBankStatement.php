<?php

namespace App\Filament\Resources\BankStatements\Pages;

use App\Filament\Resources\BankStatements\BankStatementResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBankStatement extends CreateRecord
{
    protected static string $resource = BankStatementResource::class;

    protected function afterCreate(): void
    {
        $parser = app(\App\Services\BankStatementParser::class);
        $parser->parse($this->record);
    }
}
