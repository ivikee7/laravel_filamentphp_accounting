<x-filament-panels::page>
    <div class="grid gap-4 md:grid-cols-3">
        <div class="rounded-xl border p-4">Inflows: {{ number_format($report['totals']['inflows'] ?? 0, 2) }}</div>
        <div class="rounded-xl border p-4">Outflows: {{ number_format($report['totals']['outflows'] ?? 0, 2) }}</div>
        <div class="rounded-xl border p-4 font-semibold">Net: {{ number_format($report['totals']['net'] ?? 0, 2) }}</div>
    </div>
</x-filament-panels::page>
