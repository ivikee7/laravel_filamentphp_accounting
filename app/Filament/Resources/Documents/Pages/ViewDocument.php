<?php

namespace App\Filament\Resources\Documents\Pages;

use App\Filament\Resources\Documents\DocumentResource;
use App\Models\Team;
use App\Services\Accounting\ApprovalService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ViewRecord;
use RuntimeException;

class ViewDocument extends ViewRecord
{
    protected static string $resource = DocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('submit')
                ->label('Submit for Approval')
                ->icon('heroicon-o-paper-airplane')
                ->color('warning')
                ->visible(fn (): bool => $this->record->status === 'draft')
                ->requiresConfirmation()
                ->modalHeading('Submit Document for Approval')
                ->modalDescription('This will move the document to the approval queue. Continue?')
                ->action(function (): void {
                    $tenant = Filament::getTenant();
                    if (! $tenant instanceof Team) {
                        throw new RuntimeException('Tenant is required.');
                    }
                    app(ApprovalService::class)->submitDocument($this->record, $tenant, auth()->user());
                    $this->refreshFormData(['status', 'submitted_at']);
                }),

            Action::make('approve')
                ->label('Approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (): bool => $this->record->status === 'submitted')
                ->requiresConfirmation()
                ->action(function (): void {
                    $tenant = Filament::getTenant();
                    if (! $tenant instanceof Team) {
                        throw new RuntimeException('Tenant is required.');
                    }
                    app(ApprovalService::class)->approveDocument($this->record, $tenant, auth()->user());
                    $this->refreshFormData(['status', 'approved_at']);
                }),

            Action::make('reject')
                ->label('Reject')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (): bool => $this->record->status === 'submitted')
                ->requiresConfirmation()
                ->action(function (): void {
                    $tenant = Filament::getTenant();
                    if (! $tenant instanceof Team) {
                        throw new RuntimeException('Tenant is required.');
                    }
                    app(ApprovalService::class)->rejectDocument($this->record, $tenant, auth()->user());
                    $this->refreshFormData(['status', 'rejected_at']);
                }),

            EditAction::make()
                ->visible(fn (): bool => in_array($this->record->status, ['draft', 'rejected'], true)),
        ];
    }
}