<?php

namespace App\Services\Accounting;

use App\Models\Team;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function trialBalance(Team $team): array
    {
        $rows = $this->accountRows($team);

        $lines = $rows->map(function (object $row): array {
            $balance = $row->normal_balance === 'debit'
                ? (float) $row->debit_total - (float) $row->credit_total
                : (float) $row->credit_total - (float) $row->debit_total;

            return [
                'code' => $row->code,
                'name' => $row->name,
                'type' => $row->type,
                'debit' => $balance >= 0 && $row->normal_balance === 'debit' ? round($balance, 2) : 0.0,
                'credit' => $balance >= 0 && $row->normal_balance === 'credit' ? round($balance, 2) : 0.0,
            ];
        })->values()->all();

        $debit = round((float) collect($lines)->sum('debit'), 2);
        $credit = round((float) collect($lines)->sum('credit'), 2);

        return ['lines' => $lines, 'totals' => ['debit' => $debit, 'credit' => $credit]];
    }

    public function profitAndLoss(Team $team): array
    {
        $rows = $this->accountRows($team)->filter(fn (object $row): bool => in_array($row->type, ['income', 'expense'], true));
        $income = 0.0;
        $expense = 0.0;

        foreach ($rows as $row) {
            $amount = $row->normal_balance === 'debit'
                ? (float) $row->debit_total - (float) $row->credit_total
                : (float) $row->credit_total - (float) $row->debit_total;

            if ($row->type === 'income') {
                $income += $amount;
            } else {
                $expense += $amount;
            }
        }

        $income = round($income, 2);
        $expense = round($expense, 2);

        return ['totals' => ['income' => $income, 'expense' => $expense, 'net' => round($income - $expense, 2)]];
    }

    public function balanceSheet(Team $team): array
    {
        $rows = $this->accountRows($team)->filter(fn (object $row): bool => in_array($row->type, ['asset', 'liability', 'equity'], true));
        $assets = 0.0;
        $liabilities = 0.0;
        $equity = 0.0;

        foreach ($rows as $row) {
            $amount = $row->normal_balance === 'debit'
                ? (float) $row->debit_total - (float) $row->credit_total
                : (float) $row->credit_total - (float) $row->debit_total;

            if ($row->type === 'asset') {
                $assets += $amount;
            } elseif ($row->type === 'liability') {
                $liabilities += $amount;
            } else {
                $equity += $amount;
            }
        }

        $pnl = $this->profitAndLoss($team);
        $equity += $pnl['totals']['net'];

        return [
            'totals' => [
                'assets' => round($assets, 2),
                'liabilities' => round($liabilities, 2),
                'equity' => round($equity, 2),
                'liabilities_equity' => round($liabilities + $equity, 2),
            ],
        ];
    }

    public function cashFlow(Team $team): array
    {
        $cash = DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('journal_lines.team_id', $team->getKey())
            ->where('journal_entries.status', 'posted')
            ->where('accounts.code', '1200')
            ->selectRaw('SUM(journal_lines.debit) as debit_total, SUM(journal_lines.credit) as credit_total')
            ->first();

        $inflows = round((float) ($cash->debit_total ?? 0), 2);
        $outflows = round((float) ($cash->credit_total ?? 0), 2);

        return ['totals' => ['inflows' => $inflows, 'outflows' => $outflows, 'net' => round($inflows - $outflows, 2)]];
    }

    private function accountRows(Team $team): Collection
    {
        return DB::table('accounts')
            ->leftJoin('journal_lines', function ($join): void {
                $join->on('journal_lines.account_id', '=', 'accounts.id');
            })
            ->leftJoin('journal_entries', function ($join): void {
                $join->on('journal_entries.id', '=', 'journal_lines.journal_entry_id')
                    ->where('journal_entries.status', '=', 'posted');
            })
            ->where('accounts.team_id', $team->getKey())
            ->groupBy('accounts.id', 'accounts.code', 'accounts.name', 'accounts.type', 'accounts.normal_balance')
            ->orderBy('accounts.code')
            ->selectRaw('accounts.code, accounts.name, accounts.type, accounts.normal_balance, COALESCE(SUM(journal_lines.debit),0) as debit_total, COALESCE(SUM(journal_lines.credit),0) as credit_total')
            ->get();
    }
}
