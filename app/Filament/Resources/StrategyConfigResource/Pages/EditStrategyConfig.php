<?php

namespace App\Filament\Resources\StrategyConfigResource\Pages;

use App\Filament\Resources\StrategyConfigResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditStrategyConfig extends EditRecord
{
    protected static string $resource = StrategyConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
