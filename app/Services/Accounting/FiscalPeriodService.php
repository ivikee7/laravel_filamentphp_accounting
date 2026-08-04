<?php

namespace App\Services\Accounting;

use App\Models\FiscalPeriod;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class FiscalPeriodService
{
    public function resolvePeriod(Team $team, string $date): ?FiscalPeriod
    {
        return FiscalPeriod::query()
            ->where('team_id', $team->getKey())
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->orderBy('start_date')
            ->first();
    }

    public function assertOpen(Team $team, string $date): ?FiscalPeriod
    {
        $period = $this->resolvePeriod($team, $date);
        if (! $period) {
            return null;
        }

        if ($period->is_closed) {
            throw ValidationException::withMessages([
                'period' => "Fiscal period {$period->name} is closed for {$date}.",
            ]);
        }

        return $period;
    }

    public function close(FiscalPeriod $period, User $actor): FiscalPeriod
    {
        $period->update([
            'is_closed' => true,
            'closed_at' => Carbon::now(),
            'closed_by' => $actor->getKey(),
        ]);

        return $period->refresh();
    }

    public function reopen(FiscalPeriod $period): FiscalPeriod
    {
        $period->update([
            'is_closed' => false,
            'closed_at' => null,
            'closed_by' => null,
        ]);

        return $period->refresh();
    }
}
