<x-filament-panels::page>
    <div class="grid gap-4 md:grid-cols-4">
        <div class="rounded-xl border p-4">Assets: {{ number_format($report['totals']['assets'] ?? 0, 2) }}</div>
        <div class="rounded-xl border p-4">Liabilities: {{ number_format($report['totals']['liabilities'] ?? 0, 2) }}</div>
        <div class="rounded-xl border p-4">Equity: {{ number_format($report['totals']['equity'] ?? 0, 2) }}</div>
        <div class="rounded-xl border p-4 font-semibold">L+E: {{ number_format($report['totals']['liabilities_equity'] ?? 0, 2) }}</div>
    </div>
</x-filament-panels::page>
