<?php

namespace App\Filament\Resources\BankStatements\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\FileUpload;

class BankStatementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('bank_type')
                    ->label('Banco / Tipo de Cuenta')
                    ->options([
                        'AMEX TC' => 'American Express TC (Crédito)',
                        'BANAMEX CH' => 'Citibanamex CH (Cheques)',
                        'BBVA CH' => 'BBVA CH (Cheques)',
                        'BBVA TC' => 'BBVA TC (Crédito)',
                        'BBVA US' => 'BBVA US (Dólares)',
                        'SCOTIA CH' => 'Scotiabank CH (Cheques)',
                    ])
                    ->required(),

                FileUpload::make('file_path')
                    ->label('Estado de Cuenta (PDF)')
                    ->acceptedFileTypes(['application/pdf'])
                    ->required()
                    ->storeFileNamesIn('file_name'),
            ]);
    }
}
