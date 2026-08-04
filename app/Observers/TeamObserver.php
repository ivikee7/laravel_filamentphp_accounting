<?php

namespace App\Observers;

use App\Models\Account;
use App\Models\FiscalPeriod;
use App\Models\Team;
use App\Models\TaxProfile;
use App\Models\TaxRate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class TeamObserver
{

    public function creating(Team $team): void
    {
        if (auth()->hasUser() && ! $team->owner_id) {
            $team->owner_id = Auth::id();
        }

        if (! $team->slug) {
            $baseSlug = Str::slug($team->name ?: 'team');
            $slug = $baseSlug;
            $suffix = 2;

            while (Team::query()->where('slug', $slug)->exists()) {
                $slug = "{$baseSlug}-{$suffix}";
                $suffix++;
            }

            $team->slug = $slug;
        }
    }

    /**
     * Handle the Team "created" event.
     */
    public function created(Team $team): void
    {
        $defaults = [
            ['code' => '1100', 'name' => 'Accounts Receivable', 'type' => 'asset', 'normal_balance' => 'debit'],
            ['code' => '1200', 'name' => 'Cash & Bank', 'type' => 'asset', 'normal_balance' => 'debit'],
            ['code' => '2100', 'name' => 'Accounts Payable', 'type' => 'liability', 'normal_balance' => 'credit'],
            ['code' => '3000', 'name' => 'Owner Equity', 'type' => 'equity', 'normal_balance' => 'credit'],
            ['code' => '4000', 'name' => 'Sales Revenue', 'type' => 'income', 'normal_balance' => 'credit'],
            ['code' => '5000', 'name' => 'Operating Expense', 'type' => 'expense', 'normal_balance' => 'debit'],
        ];

        foreach ($defaults as $account) {
            Account::query()->firstOrCreate(
                ['team_id' => $team->getKey(), 'code' => $account['code']],
                $account + ['is_active' => true],
            );
        }

        TaxProfile::query()->firstOrCreate(
            ['team_id' => $team->getKey()],
            [
                'country_code' => 'US',
                'currency_code' => 'USD',
                'tax_precision' => 2,
                'rounding_mode' => 'half_up',
                'prices_include_tax' => false,
            ],
        );

        $year = Carbon::now()->year;
        FiscalPeriod::query()->firstOrCreate(
            ['team_id' => $team->getKey(), 'name' => (string) $year],
            [
                'start_date' => Carbon::create($year, 1, 1)->toDateString(),
                'end_date' => Carbon::create($year, 12, 31)->toDateString(),
            ],
        );

        TaxRate::query()->firstOrCreate(
            ['team_id' => $team->getKey(), 'code' => 'STD', 'effective_from' => Carbon::create($year, 1, 1)->toDateString()],
            [
                'name' => 'Standard',
                'rate' => 0,
                'effective_to' => null,
                'is_active' => true,
            ],
        );
    }

    /**
     * Handle the Team "updated" event.
     */
    public function updated(Team $team): void
    {
        //
    }

    /**
     * Handle the Team "deleted" event.
     */
    public function deleted(Team $team): void
    {
        //
    }

    /**
     * Handle the Team "restored" event.
     */
    public function restored(Team $team): void
    {
        //
    }

    /**
     * Handle the Team "force deleted" event.
     */
    public function forceDeleted(Team $team): void
    {
        //
    }
}
