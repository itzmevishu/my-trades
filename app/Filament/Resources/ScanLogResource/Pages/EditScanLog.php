<?php

namespace App\Filament\Resources\ScanLogResource\Pages;

use App\Filament\Resources\ScanLogResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditScanLog extends EditRecord
{
    protected static string $resource = ScanLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
