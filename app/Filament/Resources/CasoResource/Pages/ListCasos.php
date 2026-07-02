<?php

namespace App\Filament\Resources\CasoResource\Pages;

use App\Filament\Resources\CasoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCasos extends ListRecords
{
    protected static string $resource = CasoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
