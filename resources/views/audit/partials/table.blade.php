@php
    $colors = [
        'sale.voided' => 'bg-red-100 text-red-800', 'sale.below_cost' => 'bg-red-100 text-red-800', 'mpesa.unverified' => 'bg-amber-100 text-amber-800',
        'shift.closed' => 'bg-amber-100 text-amber-800', 'stock.adjusted' => 'bg-purple-100 text-purple-800', 'price.changed' => 'bg-blue-100 text-blue-800',
    ];
@endphp
<div class="overflow-x-auto rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
    <table class="w-full text-left text-sm">
        <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase text-slate-500">
            <tr>
                <th class="px-4 py-3">When</th>
                <th class="px-4 py-3">Who</th>
                <th class="px-4 py-3">What</th>
                <th class="px-4 py-3">Details</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($logs as $log)
                <tr>
                    <td class="whitespace-nowrap px-4 py-3">{{ $log->created_at->format('d M, H:i') }}</td>
                    <td class="px-4 py-3">{{ $log->user?->name ?? 'System' }}</td>
                    <td class="px-4 py-3"><span class="whitespace-nowrap rounded px-2 py-0.5 text-xs font-semibold {{ $colors[$log->action] ?? 'bg-slate-100 text-slate-700' }}">{{ $log->actionLabel() }}</span></td>
                    <td class="px-4 py-3">{{ $log->description }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-4 py-10 text-center text-slate-400">Nothing recorded in these dates.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-3">{{ $logs->links() }}</div>
