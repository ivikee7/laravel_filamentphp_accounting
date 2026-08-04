<?php

namespace App\Filament\Resources\Documents\Pages;

use App\Filament\Resources\Documents\DocumentResource;
use App\Models\Team;
use App\Services\Accounting\DocumentService;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class CreateDocument extends CreateRecord
{
    protected static string $resource = DocumentResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $tenant = Filament::getTenant();
        if (! $tenant instanceof Team) {
            throw new RuntimeException('Tenant is required.');
        }

        /** @var DocumentService $service */
        $service = app(DocumentService::class);

        return $service->create($tenant, $data, auth()->user());
    }
}
