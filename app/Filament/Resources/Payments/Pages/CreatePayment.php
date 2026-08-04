<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Team;
use App\Services\Accounting\PaymentService;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class CreatePayment extends CreateRecord
{
    protected static string $resource = PaymentResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $tenant = Filament::getTenant();
        if (! $tenant instanceof Team) {
            throw new RuntimeException('Tenant is required.');
        }

        /** @var PaymentService $service */
        $service = app(PaymentService::class);

        return $service->record($tenant, $data, auth()->user());
    }
}
