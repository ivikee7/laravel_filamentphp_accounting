<?php

namespace App\Filament\Resources\TaxRates\Pages;

use App\Filament\Resources\TaxRates\TaxRateResource;
use App\Models\Team;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use RuntimeException;

class CreateTaxRate extends CreateRecord
{
    protected static string $resource = TaxRateResource::class;

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
