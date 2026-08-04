<?php

namespace App\Filament\Resources\FiscalPeriods\Pages;

use App\Filament\Resources\FiscalPeriods\FiscalPeriodResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFiscalPeriod extends EditRecord
{
    protected static string $resource = FiscalPeriodResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
