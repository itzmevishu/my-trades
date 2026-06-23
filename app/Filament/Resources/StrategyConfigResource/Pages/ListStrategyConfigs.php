<?php

namespace App\Filament\Resources\StrategyConfigResource\Pages;

use App\Filament\Resources\StrategyConfigResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStrategyConfigs extends ListRecords
{
    protected static string $resource = StrategyConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
