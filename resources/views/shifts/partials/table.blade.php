<div class="overflow-x-auto rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
    <table class="w-full text-left text-sm">
        <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase text-slate-500">
            <tr>
                <th class="px-4 py-3">Opened</th>
                <th class="px-4 py-3">Till</th>
                <th class="px-4 py-3">Cashier</th>
                <th class="px-4 py-3 text-right">Sales</th>
                <th class="px-4 py-3 text-right">Expected cash</th>
                <th class="px-4 py-3 text-right">Counted</th>
                <th class="px-4 py-3 text-right">Difference</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($shifts as $shift)
                <tr>
                    <td class="px-4 py-3 whitespace-nowrap">{{ $shift->opened_at->format('d M Y, H:i') }}</td>
                    <td class="px-4 py-3">{{ $shift->till?->code }}</td>
                    <td class="px-4 py-3">{{ $shift->user?->name }}</td>
                    <td class="px-4 py-3 text-right">{{ number_format((float) $shift->sales_total, 2) }}</td>
                    <td class="px-4 py-3 text-right">{{ $shift->expected_cash !== null ? number_format($shift->expected_cash, 2) : '-' }}</td>
                    <td class="px-4 py-3 text-right">{{ $shift->counted_cash !== null ? number_format($shift->counted_cash, 2) : '-' }}</td>
                    <td class="px-4 py-3 text-right font-semibold">
                        @if ($shift->variance === null)
                            -
                        @elseif ((float) $shift->variance == 0)
                            <span class="text-green-700">0.00</span>
                        @elseif ((float) $shift->variance < 0)
                            <span class="text-red-600">-{{ number_format(abs($shift->variance), 2) }}</span>
                        @else
                            <span class="text-amber-700">+{{ number_format($shift->variance, 2) }}</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        @if ($shift->status === 'open')
                            <span class="rounded bg-blue-100 px-2 py-0.5 text-xs text-blue-800">Open</span>
                        @else
                            <span class="rounded bg-slate-100 px-2 py-0.5 text-xs text-slate-700">Closed</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        @if ($shift->status === 'open')
                            <a href="{{ route('shifts.close', $shift) }}" class="text-red-600 hover:underline">Close</a>
                        @else
                            <a href="{{ route('shifts.report', $shift) }}" class="text-blue-600 hover:underline">Report</a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="px-4 py-10 text-center text-slate-500">No shifts yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
