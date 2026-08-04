<?php

namespace App\Filament\Pages\Reports;

use App\Services\Accounting\ReportService;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

class TrialBalanceReport extends BaseReportPage
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;

    protected static ?string $navigationLabel = 'Trial Balance';

    protected string $view = 'filament.pages.reports.trial-balance-report';

    public array $report = [];

    public function mount(): void
    {
        parent::mount();
        $this->loadReport();
    }

    public function loadReport(): void
    {
        $this->report = app(ReportService::class)->trialBalance($this->getTenant());
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Refresh')
                ->icon(Heroicon::OutlinedArrowPath)
                ->action(fn () => $this->loadReport()),
        ];
    }
}
