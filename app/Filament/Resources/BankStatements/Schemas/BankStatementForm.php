<?php

namespace App\Filament\Resources\BankStatements\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;

class BankStatementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(2)
                    ->schema([
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
                            ->required()
                            ->disabled(fn ($record) => $record !== null),

                        FileUpload::make('file_path')
                            ->label('Estado de Cuenta (PDF)')
                            ->acceptedFileTypes(['application/pdf'])
                            ->required()
                            ->storeFileNamesIn('file_name')
                            ->disabled(fn ($record) => $record !== null),
                    ]),

                Section::make('Estado del Procesamiento')
                    ->description('Detalles del proceso de extracción del PDF.')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('status')
                                    ->label('Estatus')
                                    ->disabled()
                                    ->dehydrated(false),

                                Toggle::make('is_balanced')
                                    ->label('¿Balanceado Matemáticamente?')
                                    ->disabled()
                                    ->dehydrated(false),
                            ]),

                        Textarea::make('error_message')
                            ->label('Detalle del Error')
                            ->rows(3)
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpanFull()
                            ->visible(fn ($record) => $record && $record->status === 'failed'),
                    ])
                    ->hidden(fn ($record) => $record === null)
                    ->collapsible(),

                Section::make('Información Extraída del PDF')
                    ->description('Datos financieros recuperados automáticamente.')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('account_number')
                                    ->label('Número de Cuenta')
                                    ->disabled()
                                    ->dehydrated(false),

                                TextInput::make('clabe')
                                    ->label('CLABE Interbancaria')
                                    ->disabled()
                                    ->dehydrated(false),

                                TextInput::make('saldo_inicial')
                                    ->label('Saldo Inicial (PDF)')
                                    ->prefix('$')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(false),

                                TextInput::make('saldo_final')
                                    ->label('Saldo Final (PDF)')
                                    ->prefix('$')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(false),

                                TextInput::make('total_cargos')
                                    ->label('Total Cargos (PDF)')
                                    ->prefix('$')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(false),

                                TextInput::make('total_abonos')
                                    ->label('Total Abonos (PDF)')
                                    ->prefix('$')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(false),

                                DatePicker::make('period_start')
                                    ->label('Inicio de Periodo')
                                    ->disabled()
                                    ->dehydrated(false),

                                DatePicker::make('period_end')
                                    ->label('Fin de Periodo')
                                    ->disabled()
                                    ->dehydrated(false),
                            ]),
                    ])
                    ->hidden(fn ($record) => $record === null || $record->status !== 'completed')
                    ->collapsible(),
            ]);
    }
}

