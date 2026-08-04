<?php

namespace App\Filament\Resources\Documents\Pages;

use App\Filament\Resources\Documents\DocumentResource;
use App\Models\Document;
use App\Models\DocumentLine;
use App\Models\Team;
use App\Services\Accounting\DocumentService;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class EditDocument extends EditRecord
{
    protected static string $resource = DocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [ViewAction::make(), DeleteAction::make()];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Document $record */
        $record = $this->getRecord();
        $data['lines'] = $record->lines()
            ->orderBy('line_no')
            ->get(['description', 'quantity', 'unit_price', 'tax_rate', 'tax_rate_id'])
            ->map(fn (DocumentLine $line) => $line->only(['description', 'quantity', 'unit_price', 'tax_rate', 'tax_rate_id']))
            ->all();

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $tenant = Filament::getTenant();
        if (! $tenant instanceof Team) {
            throw new RuntimeException('Tenant is required.');
        }

        /** @var DocumentService $service */
        $service = app(DocumentService::class);

        return $service->update($record, $tenant, $data, auth()->user());
    }
}
