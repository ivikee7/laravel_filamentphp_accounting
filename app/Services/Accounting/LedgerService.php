<?php

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LedgerService
{
    public function __construct(
        private readonly FiscalPeriodService $periods,
        private readonly AuditLogService $audit,
    ) {
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function post(Team $team, string $entryNo, string $entryDate, string $description, array $lines, ?User $actor = null, ?string $sourceType = null, ?int $sourceId = null): JournalEntry
    {
        if (count($lines) < 2) {
            throw new InvalidArgumentException('A journal entry requires at least two lines.');
        }

        $debit = 0.0;
        $credit = 0.0;

        foreach ($lines as $line) {
            $debit += (float) ($line['debit'] ?? 0);
            $credit += (float) ($line['credit'] ?? 0);
        }

        $debit = round($debit, 2);
        $credit = round($credit, 2);

        if ($debit !== $credit) {
            throw new InvalidArgumentException('Journal entry is unbalanced.');
        }

        return DB::transaction(function () use ($team, $entryNo, $entryDate, $description, $lines, $actor, $sourceType, $sourceId, $debit, $credit): JournalEntry {
            $period = $this->periods->assertOpen($team, $entryDate);

            $entry = JournalEntry::query()->create([
                'team_id' => $team->getKey(),
                'fiscal_period_id' => $period?->getKey(),
                'entry_no' => $entryNo,
                'entry_date' => $entryDate,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'status' => 'posted',
                'description' => $description,
                'debit_total' => $debit,
                'credit_total' => $credit,
                'created_by' => $actor?->getKey(),
            ]);

            foreach (array_values($lines) as $index => $line) {
                $accountId = (int) ($line['account_id'] ?? 0);

                $isTeamAccount = Account::query()
                    ->where('team_id', $team->getKey())
                    ->whereKey($accountId)
                    ->exists();

                if (! $isTeamAccount) {
                    throw new InvalidArgumentException('Line account must belong to team.');
                }

                $entry->lines()->create([
                    'team_id' => $team->getKey(),
                    'account_id' => $accountId,
                    'line_no' => $line['line_no'] ?? ($index + 1),
                    'description' => $line['description'] ?? null,
                    'debit' => round((float) ($line['debit'] ?? 0), 2),
                    'credit' => round((float) ($line['credit'] ?? 0), 2),
                ]);
            }

            $this->audit->append($team, $actor, 'journal.posted', JournalEntry::class, (int) $entry->getKey(), [
                'entry_no' => $entry->entry_no,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'debit_total' => $debit,
                'credit_total' => $credit,
            ]);

            return $entry->load('lines.account');
        });
    }
}
