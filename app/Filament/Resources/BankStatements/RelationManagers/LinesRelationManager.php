<?php

namespace App\Filament\Resources\BankStatements\RelationManagers;

use App\Models\BankStatementLine;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Schemas\Components\Grid;

class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    protected static ?string $title = 'Movimientos Extraídos';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(2)
                    ->schema([
                        Forms\Components\DatePicker::make('fecha')
                            ->label('Fecha')
                            ->required(),
                        Forms\Components\TextInput::make('codigo')
                            ->label('Código')
                            ->placeholder('-'),
                        Forms\Components\TextInput::make('etiqueta')
                            ->label('Concepto / Etiqueta')
                            ->required()
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('importe')
                            ->label('Importe')
                            ->numeric()
                            ->required(),
                        Forms\Components\TextInput::make('saldo')
                            ->label('Saldo')
                            ->numeric()
                            ->placeholder('-'),
                        Forms\Components\Select::make('casos')
                            ->label('Caso')
                            ->options(fn () => \App\Models\Caso::pluck('caso', 'caso')->toArray())
                            ->searchable()
                            ->placeholder('Ninguno')
                            ->nullable(),
                        Forms\Components\Select::make('contacto')
                            ->label('Contacto')
                            ->options(fn () => \App\Models\Contacto::pluck('nombre', 'nombre')->toArray())
                            ->searchable()
                            ->placeholder('Ninguno')
                            ->nullable(),
                        Forms\Components\TextInput::make('sugerencia')
                            ->label('Sugerencia')
                            ->columnSpanFull()
                            ->placeholder('-'),
                    ])
            ]);
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
            ])
            ->headerActions([
                \Filament\Actions\Action::make('aplicarCasos')
                    ->label('APLICAR CASOS')
                    ->action(function ($livewire) {
                        $statement = $livewire->getOwnerRecord();
                        
                        $casos = \App\Models\Caso::where(function ($query) use ($statement) {
                            $query->whereNull('estado_cuenta')
                                  ->orWhere('estado_cuenta', '')
                                  ->orWhere('estado_cuenta', $statement->bank_type);
                        })->get();
                        
                        $contactos = \App\Models\Contacto::all();
                        
                        foreach ($statement->lines as $line) {
                            $etiqueta = $line->etiqueta;
                            $matchedCaso = null;
                            $matchedSugerencia = null;
                            $matchedContacto = null;
                            
                            foreach ($casos as $casoItem) {
                                if (stripos($etiqueta, $casoItem->caso) !== false) {
                                    $matchedCaso = $casoItem->caso;
                                    $matchedSugerencia = $casoItem->sugerencia;
                                    if ($casoItem->contacto_sugerido) {
                                        $matchedContacto = $casoItem->contacto_sugerido;
                                    }
                                    break;
                                }
                            }
                            
                            if (!$matchedContacto) {
                                foreach ($contactos as $contactoItem) {
                                    if (stripos($etiqueta, $contactoItem->nombre) !== false) {
                                        $matchedContacto = $contactoItem->nombre;
                                        break;
                                    }
                                }
                            }
                            
                            $line->update([
                                'casos' => $matchedCaso,
                                'sugerencia' => $matchedSugerencia,
                                'contacto' => $matchedContacto,
                            ]);
                        }
                        
                        \Filament\Notifications\Notification::make()
                            ->title('Casos aplicados correctamente')
                            ->success()
                            ->send();
                    })
                    ->color('warning')
                    ->icon('heroicon-o-arrow-path'),
            ])
            ->actions([
                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
                \Filament\Actions\Action::make('crearCaso')
                    ->label('Crear Caso')
                    ->icon('heroicon-o-plus')
                    ->color('success')
                    ->form([
                        Forms\Components\TextInput::make('caso')
                            ->label('Caso / Término de búsqueda')
                            ->required()
                            ->placeholder('Ej. COMISION'),
                        
                        Forms\Components\TextInput::make('sugerencia')
                            ->label('Sugerencia')
                            ->required()
                            ->placeholder('Ej. Comisión bancaria - Sin factura'),

                        Forms\Components\Select::make('estado_cuenta')
                            ->label('Estado de Cuenta')
                            ->options([
                                'AMEX TC' => 'American Express TC (Crédito)',
                                'BANAMEX CH' => 'Citibanamex CH (Cheques)',
                                'BBVA CH' => 'BBVA CH (Cheques)',
                                'BBVA TC' => 'BBVA TC (Crédito)',
                                'BBVA US' => 'BBVA US (Dólares)',
                                'SCOTIA CH' => 'Scotiabank CH (Cheques)',
                            ])
                            ->default(fn ($livewire) => $livewire->getOwnerRecord()->bank_type)
                            ->nullable(),

                        Forms\Components\Select::make('contacto_sugerido')
                            ->label('Contacto Sugerido')
                            ->options(fn () => \App\Models\Contacto::pluck('nombre', 'nombre')->toArray())
                            ->searchable()
                            ->placeholder('Ninguno')
                            ->nullable(),

                        Forms\Components\Toggle::make('aplicar_a_linea')
                            ->label('Aplicar de inmediato a esta línea')
                            ->default(true),
                    ])
                    ->action(function (BankStatementLine $record, array $data) {
                        $caso = \App\Models\Caso::create([
                            'caso' => $data['caso'],
                            'sugerencia' => $data['sugerencia'],
                            'estado_cuenta' => $data['estado_cuenta'],
                            'contacto_sugerido' => $data['contacto_sugerido'],
                        ]);

                        if ($data['aplicar_a_linea']) {
                            $record->update([
                                'casos' => $caso->caso,
                                'sugerencia' => $caso->sugerencia,
                                'contacto' => $caso->contacto_sugerido,
                            ]);
                        }

                        \Filament\Notifications\Notification::make()
                            ->title('Caso creado y aplicado correctamente')
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
