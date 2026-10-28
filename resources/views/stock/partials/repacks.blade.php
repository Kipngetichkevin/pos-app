<h3 class="mb-2 mt-8 text-lg font-bold">Recent repacks</h3>
<div class="overflow-x-auto rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
    <table class="w-full text-left text-sm">
        <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase text-slate-500">
            <tr>
                <th class="px-4 py-3">Date</th>
                <th class="px-4 py-3">Packs made</th>
                <th class="px-4 py-3 text-right">Packed</th>
                <th class="px-4 py-3 text-right">Taken</th>
                <th class="px-4 py-3 text-right">Loss</th>
                <th class="px-4 py-3">By</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($repacks as $r)
                <tr>
                    <td class="px-4 py-3 whitespace-nowrap">{{ $r->created_at->format('d M Y, H:i') }}</td>
                    <td class="px-4 py-3">
                        @foreach ($r->packs ?? [] as $p)
                            <span class="mr-2 inline-block">{{ $p['count'] }} x {{ $p['name'] }}</span>
                        @endforeach
                    </td>
                    <td class="px-4 py-3 text-right">{{ $fq($r->packed_quantity) }}</td>
                    <td class="px-4 py-3 text-right">{{ $fq($r->bulk_used) }}</td>
                    <td class="px-4 py-3 text-right {{ $r->bulk_used > $r->packed_quantity ? 'text-amber-700' : '' }}">{{ $fq($r->bulk_used - $r->packed_quantity) }}</td>
                    <td class="px-4 py-3">{{ $r->user?->name }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">No repacks yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
