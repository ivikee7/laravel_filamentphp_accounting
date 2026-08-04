<x-filament-panels::page>
    <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-800">
        <table class="min-w-full">
            <thead>
            <tr class="border-b border-gray-200 dark:border-gray-800">
                <th class="px-4 py-3 text-left">Code</th>
                <th class="px-4 py-3 text-left">Name</th>
                <th class="px-4 py-3 text-right">Debit</th>
                <th class="px-4 py-3 text-right">Credit</th>
            </tr>
            </thead>
            <tbody>
            @foreach(($report['lines'] ?? []) as $line)
                <tr class="border-b border-gray-100 dark:border-gray-900">
                    <td class="px-4 py-3">{{ $line['code'] }}</td>
                    <td class="px-4 py-3">{{ $line['name'] }}</td>
                    <td class="px-4 py-3 text-right">{{ number_format($line['debit'], 2) }}</td>
                    <td class="px-4 py-3 text-right">{{ number_format($line['credit'], 2) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</x-filament-panels::page>
