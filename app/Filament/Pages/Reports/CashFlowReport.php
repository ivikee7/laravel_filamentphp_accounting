<?php

namespace App\Filament\Pages\Reports;

use App\Services\Accounting\ReportService;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

class CashFlowReport extends BaseReportPage
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static ?string $navigationLabel = 'Cash Flow';

    protected string $view = 'filament.pages.reports.cash-flow-report';

    public array $report = [];

    public function mount(): void
    {
        parent::mount();
        $this->loadReport();
    }

    public function loadReport(): void
    {
        $this->report = app(ReportService::class)->cashFlow($this->getTenant());
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
