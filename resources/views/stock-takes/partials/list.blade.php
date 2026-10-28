<div class="overflow-x-auto rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
    <table class="w-full text-left text-sm">
        <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase text-slate-500">
            <tr>
                <th class="px-4 py-3">Stock take</th>
                <th class="px-4 py-3">Counted</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3 text-right">Shortage</th>
                <th class="px-4 py-3 text-right">Excess</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($takes as $take)
                <tr>
                    <td class="px-4 py-3">
                        <a href="{{ route('stock-takes.show', $take) }}" class="font-mono font-semibold text-blue-600 hover:underline">{{ $take->take_no }}</a>
                        <span class="block text-xs text-slate-500">{{ $take->started_at->format('d M Y, H:i') }} &middot; {{ $take->category?->name ?? 'Whole shop' }} &middot; {{ $take->user?->name }}</span>
                    </td>
                    <td class="px-4 py-3">{{ $take->counted_count }} / {{ $take->items_count }}</td>
                    <td class="px-4 py-3">
                        <span class="rounded px-2 py-0.5 text-xs font-semibold uppercase
                            {{ $take->status === 'completed' ? 'bg-green-100 text-green-800' : ($take->status === 'counting' ? 'bg-amber-100 text-amber-800' : 'bg-slate-200 text-slate-600') }}">{{ $take->status }}</span>
                    </td>
                    <td class="px-4 py-3 text-right text-red-600">{{ $take->status === 'completed' ? number_format($take->shortage_value, 2) : '-' }}</td>
                    <td class="px-4 py-3 text-right text-green-700">{{ $take->status === 'completed' ? number_format($take->excess_value, 2) : '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-10 text-center text-slate-400">No stock takes yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-3">{{ $takes->links() }}</div>
