<?php

namespace App\Filament\Resources\BankStatements\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;

class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $title = 'Movimientos Extraídos';

    public function form(Schema $schema): Schema
    {
        return $schema;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('etiqueta')
            ->columns([
                TextColumn::make('fecha')
                    ->label('Fecha')
                    ->date('Y-m-d')
                    ->sortable(),

                TextColumn::make('codigo')
                    ->label('Código')
                    ->placeholder('-')
                    ->searchable(),

                TextColumn::make('etiqueta')
                    ->label('Concepto / Etiqueta')
                    ->searchable()
                    ->wrap(),

                TextColumn::make('casos')
                    ->label('Caso')
                    ->placeholder('-')
                    ->searchable(),

                TextColumn::make('sugerencia')
                    ->label('Sugerencia')
                    ->placeholder('-')
                    ->searchable()
                    ->wrap(),

                TextColumn::make('contacto')
                    ->label('Contacto')
                    ->placeholder('-')
                    ->searchable(),

                TextColumn::make('importe')
                    ->label('Importe')
                    ->money('MXN')
                    ->sortable()
                    ->alignment('right'),

                TextColumn::make('saldo')
                    ->label('Saldo')
                    ->money('MXN')
                    ->placeholder('-')
                    ->alignment('right'),
            ])
            ->filters([
                //
            ]);
    }
}
