<?php

namespace App\Filament\Resources\SignalCategoryResource\Pages;

use App\Filament\Resources\SignalCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSignalCategory extends EditRecord
{
    protected static string $resource = SignalCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
