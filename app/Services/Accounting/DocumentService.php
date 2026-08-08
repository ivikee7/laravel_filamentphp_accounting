<?php

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\Document;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DocumentService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly FiscalPeriodService $periods,
        private readonly TaxService $taxes,
        private readonly AuditLogService $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Team $team, array $data, ?User $actor = null): Document
    {
        return DB::transaction(function () use ($team, $data, $actor): Document {
            $type = (string) $data['type'];
            $number = ($data['number'] ?? null) ?: $this->generateNumber($team, $type);
            $issueDate = (string) ($data['issue_date'] ?? now()->toDateString());

            $this->periods->assertOpen($team, $issueDate);

            $document = Document::query()->create([
                'team_id' => $team->getKey(),
                'type' => $type,
                'number' => $number,
                'contact_id' => $data['contact_id'] ?? null,
                'tax_profile_id' => $this->taxes->defaultProfile($team)?->getKey(),
                'issue_date' => $issueDate,
                'due_date' => $data['due_date'] ?? null,
                'status' => 'draft',
                'currency_code' => $data['currency_code'] ?? 'USD',
                'supply_scope' => $data['supply_scope'] ?? 'all',
                'notes' => $data['notes'] ?? null,
            ]);

            $this->syncLines($document, $team, $data['lines'] ?? []);
            $document = $this->recalculate($document);

            $this->audit->append($team, $actor, 'document.created', Document::class, (int) $document->getKey(), [
                'number' => $document->number,
                'type' => $document->type,
                'status' => $document->status,
            ]);

            return $document->load(['contact', 'lines', 'payments']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Document $document, Team $team, array $data, ?User $actor = null): Document
    {
        if ((int) $document->team_id !== (int) $team->getKey()) {
            throw ValidationException::withMessages(['document' => 'Document does not belong to tenant.']);
        }

        if (in_array($document->status, ['submitted', 'issued', 'paid', 'partially_paid'], true)) {
            throw ValidationException::withMessages(['document' => 'Only draft or rejected documents can be edited.']);
        }

        return DB::transaction(function () use ($document, $data, $team, $actor): Document {
            $issueDate = (string) ($data['issue_date'] ?? $document->issue_date?->toDateString() ?? now()->toDateString());
            $this->periods->assertOpen($team, $issueDate);

            $document->fill([
                'contact_id' => $data['contact_id'] ?? null,
                'number' => ($data['number'] ?? null) ?: $document->number,
                'issue_date' => $issueDate,
                'due_date' => $data['due_date'] ?? null,
                'status' => $document->status === 'rejected' ? 'draft' : $document->status,
                'currency_code' => $data['currency_code'] ?? $document->currency_code,
                'supply_scope' => $data['supply_scope'] ?? $document->supply_scope ?? 'all',
                'notes' => $data['notes'] ?? null,
            ])->save();

            $this->syncLines($document, $team, $data['lines'] ?? []);

            $updated = $this->recalculate($document)->load(['contact', 'lines', 'payments']);
            $this->audit->append($team, $actor, 'document.updated', Document::class, (int) $document->getKey(), [
                'status' => $updated->status,
                'total_amount' => $updated->total_amount,
            ]);

            return $updated;
        });
    }

    public function recalculate(Document $document): Document
    {
        $document->loadMissing('lines.taxRate', 'payments');

        $subtotal = 0.0;
        $tax = 0.0;

        $breakdown = [];
        foreach ($document->lines as $line) {
            $lineSubtotal = round((float) $line->line_total - (float) $line->tax_amount, 2);
            $lineTax = (float) $line->tax_amount;

            $subtotal += $lineSubtotal;
            $tax += $lineTax;

            $lineBreakdown = is_array($line->tax_breakdown) ? $line->tax_breakdown : [];

            if ($lineBreakdown === []) {
                $lineBreakdown = [[
                    'tax_code' => $line->taxRate?->code ?? 'custom',
                    'tax_name' => $line->taxRate?->name ?? 'Custom',
                    'tax_type' => $line->taxRate?->tax_type ?? 'custom',
                    'tax_scope' => $document->supply_scope ?? 'all',
                    'tax_rate' => (float) $line->tax_rate,
                    'tax_treatment' => (string) $line->tax_treatment,
                    'taxable_amount' => $lineSubtotal,
                    'tax_amount' => $lineTax,
                    'is_recoverable' => true,
                ]];
            }

            foreach ($lineBreakdown as $component) {
                if (! is_array($component)) {
                    continue;
                }

                $taxKey = implode('|', [
                    (string) ($component['tax_code'] ?? 'custom'),
                    (string) ($component['tax_treatment'] ?? $line->tax_treatment),
                    (string) ($component['tax_rate'] ?? $line->tax_rate),
                    (string) ($component['tax_scope'] ?? $document->supply_scope ?? 'all'),
                ]);

                if (! isset($breakdown[$taxKey])) {
                    $breakdown[$taxKey] = [
                        'tax_code' => $component['tax_code'] ?? $line->taxRate?->code ?? null,
                        'tax_name' => $component['tax_name'] ?? $line->taxRate?->name ?? 'Custom',
                        'tax_type' => $component['tax_type'] ?? $line->taxRate?->tax_type ?? 'custom',
                        'tax_scope' => $component['tax_scope'] ?? $document->supply_scope ?? 'all',
                        'tax_rate' => (float) ($component['tax_rate'] ?? $line->tax_rate),
                        'tax_treatment' => (string) ($component['tax_treatment'] ?? $line->tax_treatment),
                        'taxable_amount' => 0.0,
                        'tax_amount' => 0.0,
                        'is_recoverable' => (bool) ($component['is_recoverable'] ?? true),
                    ];
                }

                $breakdown[$taxKey]['taxable_amount'] = round($breakdown[$taxKey]['taxable_amount'] + (float) ($component['taxable_amount'] ?? $lineSubtotal), 2);
                $breakdown[$taxKey]['tax_amount'] = round($breakdown[$taxKey]['tax_amount'] + (float) ($component['tax_amount'] ?? 0), 2);
            }
        }

        foreach ($breakdown as $key => $item) {
            if ($item['tax_treatment'] === 'exempt') {
                $breakdown[$key]['tax_rate'] = 0.0;
            }
        }

        $total = round($subtotal + $tax, 2);
        $paid = round((float) $document->payments->sum('amount'), 2);
        $balance = round($total - $paid, 2);

        $status = $document->status;
        if (in_array($status, ['issued', 'partially_paid', 'paid', 'overdue'], true)) {
            if ($balance <= 0 && $total > 0) {
                $status = 'paid';
            } elseif ($paid > 0) {
                $status = 'partially_paid';
            } elseif ($document->due_date && now()->isAfter($document->due_date)) {
                $status = 'overdue';
            } else {
                $status = 'issued';
            }
        }

        $document->update([
            'subtotal_amount' => round($subtotal, 2),
            'tax_amount' => round($tax, 2),
            'tax_breakdown' => array_values($breakdown),
            'total_amount' => $total,
            'paid_amount' => $paid,
            'balance_due' => $balance,
            'status' => $status,
        ]);

        return $document->refresh();
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function syncLines(Document $document, Team $team, array $lines): void
    {
        $document->lines()->delete();
        $issueDate = $document->issue_date?->toDateString() ?? now()->toDateString();

        foreach (array_values($lines) as $index => $line) {
            $qty = round((float) ($line['quantity'] ?? 0), 4);
            $unitPrice = round((float) ($line['unit_price'] ?? 0), 2);
            $taxRate = round((float) ($line['tax_rate'] ?? 0), 4);
            $taxTreatment = (string) ($line['tax_treatment'] ?? 'taxable');

            if ($qty <= 0 || $unitPrice < 0 || $taxRate < 0 || $taxRate > 100) {
                throw ValidationException::withMessages(['lines' => 'Line values are invalid.']);
            }

            if (! in_array($taxTreatment, ['taxable', 'zero_rated', 'exempt'], true)) {
                throw ValidationException::withMessages(['lines' => 'Tax treatment is invalid.']);
            }

            $lineContext = $line;
            $lineContext['supply_scope'] = $document->supply_scope ?? 'all';

            $tax = $this->taxes->calculateLine($team, $issueDate, $lineContext);

            $document->lines()->create([
                'team_id' => $team->getKey(),
                'line_no' => $index + 1,
                'description' => $line['description'] ?? null,
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'price_includes_tax' => $tax['price_includes_tax'],
                'tax_rate' => $tax['tax_rate'],
                'tax_rate_id' => $tax['tax_rate_id'],
                'tax_treatment' => $tax['tax_treatment'],
                'tax_amount' => $tax['tax_amount'],
                'tax_breakdown' => $tax['tax_breakdown'],
                'line_total' => $tax['total'],
            ]);
        }
    }

    public function postIssuedDocument(Document $document, Team $team, ?User $actor): void
    {
        $alreadyPosted = $team->journalEntries()
            ->where('source_type', 'document')
            ->where('source_id', $document->getKey())
            ->exists();

        if ($alreadyPosted) {
            return;
        }

        $this->periods->assertOpen($team, $document->issue_date->toDateString());

        $receivable = $this->findRequiredAccount($team, '1100');
        $payable = $this->findRequiredAccount($team, '2100');
        $revenue = $this->findRequiredAccount($team, '4000');
        $expense = $this->findRequiredAccount($team, '5000');
        $inputTax = $this->findRequiredAccount($team, '1300');
        $outputTax = $this->findRequiredAccount($team, '2200');

        $subtotalAmount = (float) $document->subtotal_amount;
        $taxAmount = (float) $document->tax_amount;
        $totalAmount = (float) $document->total_amount;

        if ($document->type === 'invoice') {
            $lines = [
                ['account_id' => $receivable->getKey(), 'debit' => $totalAmount, 'credit' => 0, 'description' => 'Invoice receivable'],
                ['account_id' => $revenue->getKey(), 'debit' => 0, 'credit' => $subtotalAmount, 'description' => 'Invoice revenue'],
            ];

            if ($taxAmount > 0) {
                $lines[] = ['account_id' => $outputTax->getKey(), 'debit' => 0, 'credit' => $taxAmount, 'description' => 'Output tax payable'];
            }
        } else {
            $lines = [
                ['account_id' => $expense->getKey(), 'debit' => $subtotalAmount, 'credit' => 0, 'description' => 'Bill expense'],
                ['account_id' => $payable->getKey(), 'debit' => 0, 'credit' => $totalAmount, 'description' => 'Bill payable'],
            ];

            if ($taxAmount > 0) {
                $lines[] = ['account_id' => $inputTax->getKey(), 'debit' => $taxAmount, 'credit' => 0, 'description' => 'Input tax recoverable'];
            }
        }

        $this->ledger->post(
            $team,
            $this->nextEntryNo($team),
            $document->issue_date->toDateString(),
            strtoupper($document->type).' '.$document->number,
            $lines,
            $actor,
            'document',
            $document->getKey(),
        );

        $this->audit->append($team, $actor, 'document.posted', Document::class, (int) $document->getKey(), [
            'entry_date' => $document->issue_date->toDateString(),
            'total_amount' => $document->total_amount,
        ]);
    }

    private function nextEntryNo(Team $team): string
    {
        $count = $team->journalEntries()->count() + 1;

        return sprintf('JE-%06d', $count);
    }

    private function generateNumber(Team $team, string $type): string
    {
        $prefix = $type === 'invoice' ? 'INV' : 'BIL';
        $count = Document::query()->where('team_id', $team->getKey())->where('type', $type)->count() + 1;

        return sprintf('%s-%06d', $prefix, $count);
    }

    private function findRequiredAccount(Team $team, string $code): Account
    {
        return Account::query()
            ->where('team_id', $team->getKey())
            ->where('code', $code)
            ->firstOrFail();
    }
}
