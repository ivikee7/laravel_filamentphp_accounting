<?php

namespace App\Filament\Resources\FiscalPeriods\Pages;

use App\Filament\Resources\FiscalPeriods\FiscalPeriodResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFiscalPeriods extends ListRecords
{
    protected static string $resource = FiscalPeriodResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
