<?php

namespace App\Filament\Resources\FiscalPeriods\Pages;

use App\Filament\Resources\FiscalPeriods\FiscalPeriodResource;
use App\Models\Team;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use RuntimeException;

class CreateFiscalPeriod extends CreateRecord
{
    protected static string $resource = FiscalPeriodResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $tenant = Filament::getTenant();
        if (! $tenant instanceof Team) {
            throw new RuntimeException('Tenant is required.');
        }

        $data['team_id'] = $tenant->getKey();

        return $data;
    }
}
