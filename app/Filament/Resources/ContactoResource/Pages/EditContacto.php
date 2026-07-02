<?php

namespace App\Filament\Resources\ContactoResource\Pages;

use App\Filament\Resources\ContactoResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditContacto extends EditRecord
{
    protected static string $resource = ContactoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
