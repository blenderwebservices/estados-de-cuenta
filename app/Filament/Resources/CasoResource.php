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
