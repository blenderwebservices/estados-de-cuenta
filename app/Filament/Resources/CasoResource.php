<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CasoResource\Pages\CreateCaso;
use App\Filament\Resources\CasoResource\Pages\EditCaso;
use App\Filament\Resources\CasoResource\Pages\ListCasos;
use App\Models\Caso;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;

class CasoResource extends Resource
{
    protected static ?string $model = Caso::class;

    protected static ?string $modelLabel = 'Caso';
    protected static ?string $pluralModelLabel = 'Casos';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('caso')
                    ->label('Caso / Término de búsqueda')
                    ->required()
                    ->placeholder('Ej. COMISION'),
                
                TextInput::make('sugerencia')
                    ->label('Sugerencia')
                    ->required()
                    ->placeholder('Ej. Comisión bancaria - Sin factura'),

                Select::make('estado_cuenta')
                    ->label('Estado de Cuenta')
                    ->options([
                        'AMEX TC' => 'American Express TC (Crédito)',
                        'BANAMEX CH' => 'Citibanamex CH (Cheques)',
                        'BBVA CH' => 'BBVA CH (Cheques)',
                        'BBVA TC' => 'BBVA TC (Crédito)',
                        'BBVA US' => 'BBVA US (Dólares)',
                        'SCOTIA CH' => 'Scotiabank CH (Cheques)',
                    ])
                    ->placeholder('Todos los estados de cuenta')
                    ->nullable(),

                Select::make('contacto_sugerido')
                    ->label('Contacto Sugerido')
                    ->options(fn () => \App\Models\Contacto::pluck('nombre', 'nombre')->toArray())
                    ->searchable()
                    ->placeholder('Ninguno / Autodetectar')
                    ->nullable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('caso')
                    ->label('Caso / Término')
                    ->searchable()
                    ->sortable(),
                
                TextColumn::make('sugerencia')
                    ->label('Sugerencia')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make('estado_cuenta')
                    ->label('Estado de Cuenta')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Todos'),

                TextColumn::make('contacto_sugerido')
                    ->label('Contacto Sugerido')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Ninguno'),
            ])
            ->filters([])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCasos::route('/'),
            'create' => CreateCaso::route('/create'),
            'edit' => EditCaso::route('/{record}/edit'),
        ];
    }
}
