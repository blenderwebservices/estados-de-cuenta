<?php

namespace App\Filament\Resources\CasoResource\Pages;

use App\Filament\Resources\CasoResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCaso extends EditRecord
{
    protected static string $resource = CasoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
