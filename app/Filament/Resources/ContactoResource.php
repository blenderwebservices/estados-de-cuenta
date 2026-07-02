<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContactoResource\Pages\CreateContacto;
use App\Filament\Resources\ContactoResource\Pages\EditContacto;
use App\Filament\Resources\ContactoResource\Pages\ListContactos;
use App\Models\Contacto;
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

class ContactoResource extends Resource
{
    protected static ?string $model = Contacto::class;

    protected static ?string $modelLabel = 'Contacto';
    protected static ?string $pluralModelLabel = 'Contactos';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nombre')
                    ->label('Nombre del Contacto')
                    ->required()
                    ->placeholder('Ej. JUAN PEREZ'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
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
            'index' => ListContactos::route('/'),
            'create' => CreateContacto::route('/create'),
            'edit' => EditContacto::route('/{record}/edit'),
        ];
    }
}
