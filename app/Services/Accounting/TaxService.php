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
     * @return array{
     *     tax_rate: float,
     *     tax_rate_id: int|null,
     *     tax_amount: float,
     *     subtotal: float,
     *     total: float,
     *     price_includes_tax: bool,
     *     tax_treatment: string,
     *     tax_breakdown: array<int, array<string, mixed>>
     * }
     */
    public function calculateLine(Team $team, string $issueDate, array $line): array
    {
        $profile = $this->defaultProfile($team);
        $precision = max(0, min(4, (int) ($profile?->tax_precision ?? 2)));
        $roundingMode = (string) ($profile?->rounding_mode ?? 'half_up');
        $pricesIncludeTax = array_key_exists('price_includes_tax', $line)
            ? (bool) $line['price_includes_tax']
            : (bool) ($profile?->prices_include_tax ?? false);
        $taxTreatment = (string) ($line['tax_treatment'] ?? 'taxable');
        $supplyScope = (string) ($line['supply_scope'] ?? 'all');
        $quantity = round((float) ($line['quantity'] ?? 0), 4);
        $unitPrice = (float) ($line['unit_price'] ?? 0);
        $lineAmount = $this->roundAmount($quantity * $unitPrice, $precision, $roundingMode);

        $taxRateId = isset($line['tax_rate_id']) ? (int) $line['tax_rate_id'] : null;
        $taxRate = round((float) ($line['tax_rate'] ?? 0), 4);
        $taxType = (string) ($line['tax_type'] ?? 'custom');
        $components = [];

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

            if (
                $record &&
                $record->applies_to_scope !== 'all' &&
                ! in_array($record->applies_to_scope, [$supplyScope, 'domestic'], true)
            ) {
                $record = null;
            }

            if ($record) {
                $taxRate = (float) $record->rate;
                $taxRateId = (int) $record->getKey();
                $taxType = (string) $record->tax_type;
                $components = $this->normalizedComponents($record);

                if (array_key_exists('tax_treatment', $line) === false && in_array($record->category, ['zero_rated', 'exempt'], true)) {
                    $taxTreatment = $record->category;
                }
            }
        }

        if ($components === []) {
            $components = [[
                'tax_code' => (string) ($line['tax_code'] ?? 'TAX'),
                'tax_name' => (string) ($line['tax_name'] ?? 'Tax'),
                'tax_type' => $taxType,
                'tax_scope' => $supplyScope,
                'tax_rate' => $taxRate,
                'is_recoverable' => true,
            ]];
        }

        if ($taxTreatment === 'exempt') {
            $taxRate = 0.0;
            $taxAmount = 0.0;
            $subtotal = $lineAmount;
            $total = $lineAmount;
            $taxBreakdown = $this->finalizeBreakdown($components, $subtotal, array_fill(0, count($components), 0.0), $taxTreatment, $precision);
        } else {
            if ($taxTreatment === 'zero_rated') {
                foreach ($components as $index => $component) {
                    $components[$index]['tax_rate'] = 0.0;
                }
            }

            $aggregateRate = round((float) collect($components)->sum('tax_rate'), 4);
            $taxRate = $aggregateRate;

            if ($pricesIncludeTax && $aggregateRate > 0) {
                $subtotal = $this->roundAmount($lineAmount / (1 + ($aggregateRate / 100)), $precision, $roundingMode);
                $taxAmount = $this->roundAmount($lineAmount - $subtotal, $precision, $roundingMode);
                $total = $lineAmount;
            } else {
                $subtotal = $lineAmount;
                $taxAmount = $this->roundAmount($subtotal * ($aggregateRate / 100), $precision, $roundingMode);
                $total = $this->roundAmount($subtotal + $taxAmount, $precision, $roundingMode);
            }

            $componentAmounts = $this->allocateComponentAmounts($components, $taxAmount, $precision, $roundingMode);
            $taxBreakdown = $this->finalizeBreakdown($components, $subtotal, $componentAmounts, $taxTreatment, $precision);
        }

        return [
            'tax_rate' => $taxRate,
            'tax_rate_id' => $taxRateId,
            'tax_amount' => $taxAmount,
            'subtotal' => $subtotal,
            'total' => $total,
            'price_includes_tax' => $pricesIncludeTax,
            'tax_treatment' => $taxTreatment,
            'tax_breakdown' => $taxBreakdown,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizedComponents(TaxRate $record): array
    {
        if (! is_array($record->components) || $record->components === []) {
            return [[
                'tax_code' => $record->code,
                'tax_name' => $record->name,
                'tax_type' => $record->tax_type,
                'tax_scope' => $record->applies_to_scope,
                'tax_rate' => (float) $record->rate,
                'is_recoverable' => (bool) $record->is_recoverable,
            ]];
        }

        return collect($record->components)
            ->filter(fn (mixed $component): bool => is_array($component))
            ->map(function (array $component) use ($record): array {
                return [
                    'tax_code' => (string) ($component['tax_code'] ?? $component['code'] ?? $record->code),
                    'tax_name' => (string) ($component['tax_name'] ?? $component['name'] ?? $record->name),
                    'tax_type' => (string) ($component['tax_type'] ?? $record->tax_type),
                    'tax_scope' => (string) ($component['tax_scope'] ?? $component['scope'] ?? $record->applies_to_scope),
                    'tax_rate' => round((float) ($component['tax_rate'] ?? $component['rate'] ?? 0), 4),
                    'is_recoverable' => (bool) ($component['is_recoverable'] ?? $record->is_recoverable),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $components
     * @return array<int, float>
     */
    private function allocateComponentAmounts(array $components, float $taxAmount, int $precision, string $mode): array
    {
        $rates = collect($components)->map(fn (array $component): float => (float) $component['tax_rate'])->values();
        $totalRate = (float) $rates->sum();

        if ($taxAmount <= 0 || $totalRate <= 0) {
            return array_fill(0, count($components), 0.0);
        }

        $allocated = [];
        $running = 0.0;

        foreach ($rates as $index => $rate) {
            if ($index === $rates->count() - 1) {
                $allocated[] = $this->roundAmount($taxAmount - $running, $precision, $mode);

                continue;
            }

            $portion = $this->roundAmount($taxAmount * ($rate / $totalRate), $precision, $mode);
            $allocated[] = $portion;
            $running = $this->roundAmount($running + $portion, $precision, $mode);
        }

        return $allocated;
    }

    /**
     * @param  array<int, array<string, mixed>>  $components
     * @param  array<int, float>  $componentAmounts
     * @return array<int, array<string, mixed>>
     */
    private function finalizeBreakdown(array $components, float $subtotal, array $componentAmounts, string $taxTreatment, int $precision): array
    {
        return collect($components)->values()->map(function (array $component, int $index) use ($subtotal, $componentAmounts, $taxTreatment, $precision): array {
            return [
                'tax_code' => (string) ($component['tax_code'] ?? 'TAX'),
                'tax_name' => (string) ($component['tax_name'] ?? 'Tax'),
                'tax_type' => (string) ($component['tax_type'] ?? 'custom'),
                'tax_scope' => (string) ($component['tax_scope'] ?? 'all'),
                'tax_rate' => round((float) ($component['tax_rate'] ?? 0), 4),
                'tax_treatment' => $taxTreatment,
                'taxable_amount' => $this->roundAmount($subtotal, $precision, 'half_up'),
                'tax_amount' => $this->roundAmount((float) ($componentAmounts[$index] ?? 0), $precision, 'half_up'),
                'is_recoverable' => (bool) ($component['is_recoverable'] ?? true),
            ];
        })->all();
    }

    private function roundAmount(float $value, int $precision, string $mode): float
    {
        return match ($mode) {
            'half_down' => round($value, $precision, PHP_ROUND_HALF_DOWN),
            'half_even' => round($value, $precision, PHP_ROUND_HALF_EVEN),
            'floor' => floor($value * (10 ** $precision)) / (10 ** $precision),
            'ceil' => ceil($value * (10 ** $precision)) / (10 ** $precision),
            default => round($value, $precision, PHP_ROUND_HALF_UP),
        };
    }
}
