<?php

namespace App\Filament\Resources\BankStatements;

use App\Filament\Resources\BankStatements\Pages\CreateBankStatement;
use App\Filament\Resources\BankStatements\Pages\EditBankStatement;
use App\Filament\Resources\BankStatements\Pages\ListBankStatements;
use App\Filament\Resources\BankStatements\Pages\ViewBankStatement;
use App\Filament\Resources\BankStatements\Schemas\BankStatementForm;
use App\Filament\Resources\BankStatements\Tables\BankStatementsTable;
use App\Models\BankStatement;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class BankStatementResource extends Resource
{
    protected static ?string $model = BankStatement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return BankStatementForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BankStatementsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\LinesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBankStatements::route('/'),
            'create' => CreateBankStatement::route('/create'),
            'view' => ViewBankStatement::route('/{record}'),
            'edit' => EditBankStatement::route('/{record}/edit'),
        ];
    }
}
