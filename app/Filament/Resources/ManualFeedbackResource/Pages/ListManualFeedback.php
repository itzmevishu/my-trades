<?php

namespace App\Filament\Resources\ManualFeedbackResource\Pages;

use App\Filament\Resources\ManualFeedbackResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListManualFeedback extends ListRecords
{
    protected static string $resource = ManualFeedbackResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
