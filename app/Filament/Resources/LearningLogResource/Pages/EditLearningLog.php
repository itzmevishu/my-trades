<?php

namespace App\Filament\Resources\LearningLogResource\Pages;

use App\Filament\Resources\LearningLogResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLearningLog extends EditRecord
{
    protected static string $resource = LearningLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
