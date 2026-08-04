<?php

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\Document;
use App\Models\Payment;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(
        private readonly DocumentService $documents,
        private readonly LedgerService $ledger,
        private readonly FiscalPeriodService $periods,
        private readonly AuditLogService $audit,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function record(Team $team, array $data, ?User $actor = null): Payment
    {
        $documentId = (int) ($data['document_id'] ?? 0);
        $amount = round((float) ($data['amount'] ?? 0), 2);

        if ($documentId <= 0 || $amount <= 0) {
            throw ValidationException::withMessages(['payment' => 'Document and amount are required.']);
        }

        $document = Document::query()
            ->where('team_id', $team->getKey())
            ->whereKey($documentId)
            ->first();

        if (! $document) {
            throw ValidationException::withMessages(['document_id' => 'Document must belong to current team.']);
        }

        if (in_array($document->status, ['draft', 'submitted', 'rejected'], true)) {
            throw ValidationException::withMessages(['document_id' => 'Payments are allowed only for approved and issued documents.']);
        }

        return DB::transaction(function () use ($team, $data, $actor, $document, $amount): Payment {
            $paymentDate = (string) ($data['payment_date'] ?? now()->toDateString());
            $this->periods->assertOpen($team, $paymentDate);

            $payment = Payment::query()->create([
                'team_id' => $team->getKey(),
                'document_id' => $document->getKey(),
                'payment_date' => $paymentDate,
                'amount' => $amount,
                'method' => $data['method'] ?? 'bank_transfer',
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor?->getKey(),
            ]);

            $cash = $this->findRequiredAccount($team, '1200');
            $receivable = $this->findRequiredAccount($team, '1100');
            $payable = $this->findRequiredAccount($team, '2100');

            if ($document->type === 'invoice') {
                $lines = [
                    ['account_id' => $cash->getKey(), 'debit' => $amount, 'credit' => 0, 'description' => 'Cash received'],
                    ['account_id' => $receivable->getKey(), 'debit' => 0, 'credit' => $amount, 'description' => 'Receivable settled'],
                ];
            } else {
                $lines = [
                    ['account_id' => $payable->getKey(), 'debit' => $amount, 'credit' => 0, 'description' => 'Payable settled'],
                    ['account_id' => $cash->getKey(), 'debit' => 0, 'credit' => $amount, 'description' => 'Cash paid'],
                ];
            }

            $this->ledger->post(
                $team,
                sprintf('JE-%06d', $team->journalEntries()->count() + 1),
                $payment->payment_date->toDateString(),
                'Payment for ' . strtoupper($document->type) . ' ' . $document->number,
                $lines,
                $actor,
                'payment',
                $payment->getKey(),
            );

            $this->documents->recalculate($document);
            $this->audit->append($team, $actor, 'payment.recorded', Payment::class, (int) $payment->getKey(), [
                'document_id' => $document->getKey(),
                'amount' => $amount,
                'payment_date' => $paymentDate,
            ]);

            return $payment->refresh();
        });
    }

    private function findRequiredAccount(Team $team, string $code): Account
    {
        return Account::query()
            ->where('team_id', $team->getKey())
            ->where('code', $code)
            ->firstOrFail();
    }
}
