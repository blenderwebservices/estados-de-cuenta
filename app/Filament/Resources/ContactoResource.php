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
use Filament\Forms\Components\Toggle;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
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
                TextInput::make('id_externo')
                    ->label('ID Externo')
                    ->placeholder('Ej. __export__.res_partner_xxxx'),
                
                TextInput::make('nombre')
                    ->label('Nombre del Contacto')
                    ->required()
                    ->placeholder('Ej. JUAN PEREZ'),

                TextInput::make('email')
                    ->label('Correo Electrónico')
                    ->email()
                    ->placeholder('Ej. contacto@empresa.com'),

                TextInput::make('telefono')
                    ->label('Teléfono')
                    ->placeholder('Ej. 5512345678'),

                Toggle::make('esempresa')
                    ->label('Es Empresa')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id_externo')
                    ->label('ID Externo')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('nombre')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label('Correo Electrónico')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('telefono')
                    ->label('Teléfono')
                    ->searchable()
                    ->sortable(),

                IconColumn::make('esempresa')
                    ->label('Es Empresa')
                    ->boolean()
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
