<?php

namespace App\Filament\Resources\BankStatements\Tables;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;
use App\Services\ExcelExporter;
use Illuminate\Support\Facades\Storage;

class BankStatementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('file_name')
                    ->label('Archivo')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('bank_type')
                    ->label('Banco')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('account_number')
                    ->label('Cuenta')
                    ->searchable()
                    ->placeholder('-'),

                TextColumn::make('period_start')
                    ->label('Periodo')
                    ->state(fn ($record) => $record->period_start && $record->period_end 
                        ? $record->period_start->format('Y-m-d') . ' al ' . $record->period_end->format('Y-m-d')
                        : '-'
                    ),

                TextColumn::make('total_cargos')
                    ->label('Cargos (PDF)')
                    ->money('MXN')
                    ->sortable()
                    ->alignment('right'),

                TextColumn::make('total_abonos')
                    ->label('Abonos (PDF)')
                    ->money('MXN')
                    ->sortable()
                    ->alignment('right'),

                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'failed' => 'danger',
                        'processing' => 'warning',
                        'pending' => 'gray',
                        default => 'gray',
                    }),

                IconColumn::make('is_balanced')
                    ->label('Cuadre')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),
            ])
            ->filters([
                //
            ])
            ->actions([
                Action::make('download_excel')
                    ->label('Excel')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
                    ->action(function ($record) {
                        $exporter = app(ExcelExporter::class);
                        $filePath = $exporter->export($record);
                        
                        return response()->download($filePath, str_replace('.pdf', '.xlsx', $record->file_name))->deleteFileAfterSend(true);
                    })
                    ->visible(fn ($record) => $record->status === 'completed'),
                    
                ViewAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
