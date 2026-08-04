<x-filament-panels::page>
    <div class="grid gap-4 md:grid-cols-3">
        <div class="rounded-xl border p-4">Income: {{ number_format($report['totals']['income'] ?? 0, 2) }}</div>
        <div class="rounded-xl border p-4">Expense: {{ number_format($report['totals']['expense'] ?? 0, 2) }}</div>
        <div class="rounded-xl border p-4 font-semibold">Net: {{ number_format($report['totals']['net'] ?? 0, 2) }}</div>
    </div>
</x-filament-panels::page>
