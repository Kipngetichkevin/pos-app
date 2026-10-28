<div class="overflow-x-auto rounded-2xl bg-white shadow-sm ring-1 ring-slate-200">
    <table class="w-full text-left text-sm">
        <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase text-slate-500">
            <tr>
                <th class="px-4 py-3">Date</th>
                <th class="px-4 py-3">For</th>
                <th class="px-4 py-3">Paid by</th>
                <th class="px-4 py-3 text-right">Amount</th>
                @if (auth()->user()->isManager()) <th class="px-4 py-3"></th> @endif
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($expenses as $expense)
                <tr>
                    <td class="whitespace-nowrap px-4 py-3">
                        {{ $expense->spent_at->format('d M, H:i') }}
                        <span class="block text-xs text-slate-500">{{ $expense->user?->name }}</span>
                    </td>
                    <td class="px-4 py-3">
                        <span class="font-medium">{{ $expense->description }}</span>
                        <span class="block text-xs text-slate-500">{{ $expense->categoryLabel() }}{{ $expense->paid_to ? ' · to ' . $expense->paid_to : '' }}</span>
                    </td>
                    <td class="px-4 py-3 text-xs uppercase">
                        {{ $expense->method }}
                        @if ($expense->reference) <span class="block font-mono normal-case text-slate-500">{{ $expense->reference }}</span> @endif
                    </td>
                    <td class="px-4 py-3 text-right font-semibold">{{ number_format($expense->amount, 2) }}</td>
                    @if (auth()->user()->isManager())
                        <td class="px-4 py-3 text-right">
                            <form method="POST" action="{{ route('expenses.destroy', $expense) }}" onsubmit="return confirm('Remove this expense?')">
                                @csrf @method('DELETE')
                                <button class="text-xs text-red-600 hover:underline">Remove</button>
                            </form>
                        </td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="5" class="px-4 py-10 text-center text-slate-400">No expenses in these dates.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-3">{{ $expenses->links() }}</div>
