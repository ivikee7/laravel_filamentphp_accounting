<?php

namespace App\Services\Accounting;

use App\Models\TaxProfile;
use App\Models\TaxRate;
use App\Models\Team;

class TaxService
{
    public function defaultProfile(Team $team): ?TaxProfile
    {
        return TaxProfile::query()->where('team_id', $team->getKey())->first();
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array{tax_rate: float, tax_rate_id: int|null, tax_amount: float, subtotal: float, total: float}
     */
    public function calculateLine(Team $team, string $issueDate, array $line): array
    {
        $quantity = round((float) ($line['quantity'] ?? 0), 4);
        $unitPrice = round((float) ($line['unit_price'] ?? 0), 2);
        $subtotal = round($quantity * $unitPrice, 2);

        $taxRateId = isset($line['tax_rate_id']) ? (int) $line['tax_rate_id'] : null;
        $taxRate = round((float) ($line['tax_rate'] ?? 0), 4);

        if ($taxRateId) {
            $record = TaxRate::query()
                ->where('team_id', $team->getKey())
                ->whereKey($taxRateId)
                ->where('is_active', true)
                ->whereDate('effective_from', '<=', $issueDate)
                ->where(function ($query) use ($issueDate): void {
                    $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $issueDate);
                })
                ->first();

            if ($record) {
                $taxRate = (float) $record->rate;
                $taxRateId = (int) $record->getKey();
            }
        }

        $taxAmount = round($subtotal * ($taxRate / 100), 2);

        return [
            'tax_rate' => $taxRate,
            'tax_rate_id' => $taxRateId,
            'tax_amount' => $taxAmount,
            'subtotal' => $subtotal,
            'total' => round($subtotal + $taxAmount, 2),
        ];
    }
}
