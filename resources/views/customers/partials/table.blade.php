@php $keep = request()->only(['search', 'filter', 'page']); @endphp
<div class="overflow-x-auto rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
    <table class="w-full text-left text-sm">
        <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase text-slate-500">
            <tr>
                <th class="px-4 py-3">Customer</th>
                <th class="px-4 py-3">Phone</th>
                <th class="px-4 py-3 text-right">Credit limit</th>
                <th class="px-4 py-3 text-right">Owes</th>
                <th class="px-4 py-3 text-right">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($customers as $cust)
                <tr class="{{ $cust->is_active ? '' : 'opacity-50' }}">
                    <td class="px-4 py-3 font-medium">{{ $cust->name }}</td>
                    <td class="px-4 py-3">{{ $cust->phone ?? '-' }}</td>
                    <td class="px-4 py-3 text-right">{{ (float) $cust->credit_limit > 0 ? number_format($cust->credit_limit, 2) : 'No credit' }}</td>
                    <td class="px-4 py-3 text-right font-semibold {{ (float) $cust->balance > 0 ? 'text-red-600' : 'text-slate-500' }}">{{ number_format($cust->balance, 2) }}</td>
                    <td class="whitespace-nowrap px-4 py-3 text-right">
                        @if ((float) $cust->balance > 0)
                            <a href="{{ route('customers.index', array_merge($keep, ['pay' => $cust->id])) }}" class="font-semibold text-green-700 hover:underline">Receive payment</a>
                        @endif
                        <a href="{{ route('customers.index', array_merge($keep, ['statement' => $cust->id])) }}" class="ml-3 text-slate-600 hover:underline">Statement</a>
                        <a href="{{ route('customers.index', array_merge($keep, ['edit' => $cust->id])) }}" class="ml-3 text-blue-600 hover:underline">Edit</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-10 text-center text-slate-500">{{ $filter === 'owing' ? 'Nobody owes money right now.' : 'No customers yet.' }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
