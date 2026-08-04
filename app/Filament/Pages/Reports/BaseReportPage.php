<?php

namespace App\Filament\Pages\Reports;

use App\Models\Team;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use UnitEnum;

abstract class BaseReportPage extends Page
{
    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    public ?string $dateFrom = null;
    public ?string $dateTo = null;

    public function mount(): void
    {
        $this->dateFrom = now()->startOfYear()->toDateString();
        $this->dateTo = now()->toDateString();
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();
        $tenant = Filament::getTenant();

        return $user instanceof User && $tenant instanceof Team && $user->canManageAccounting($tenant);
    }

    protected function getTenant(): Team
    {
        $tenant = Filament::getTenant();
        if (! $tenant instanceof Team) {
            abort(403);
        }

        return $tenant;
    }

    protected function getFilterFormSchema(): array
    {
        return [
            DatePicker::make('dateFrom')->label('From')->default(now()->startOfYear()),
            DatePicker::make('dateTo')->label('To')->default(now()),
        ];
    }
}
