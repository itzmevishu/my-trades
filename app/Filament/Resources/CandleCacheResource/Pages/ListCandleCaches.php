<?php

namespace App\Filament\Resources\CandleCacheResource\Pages;

use App\Filament\Resources\CandleCacheResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCandleCaches extends ListRecords
{
    protected static string $resource = CandleCacheResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
