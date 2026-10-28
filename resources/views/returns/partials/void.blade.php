<details class="mt-4 rounded-xl border border-red-200 p-3">
    <summary class="cursor-pointer text-sm font-semibold text-red-700">Void the entire sale</summary>
    <form method="POST" action="{{ route('returns.void', $sale) }}" class="mt-3 grid gap-3 md:grid-cols-3"
          onsubmit="return confirm(@js('Void the WHOLE sale ' . $sale->receipt_no . '? All items go back into stock.'))">
        @csrf
        <select name="reason" class="rounded-lg border border-slate-300 px-3 py-2 {{ $needsPin ? '' : 'md:col-span-2' }}">
            <option value="">-- Reason --</option>
            @foreach ($reasons as $r)
                <option value="{{ $r }}">{{ $r }}</option>
            @endforeach
        </select>
        @if ($needsPin)
            <input name="pin" type="password" inputmode="numeric" autocomplete="off" placeholder="Manager PIN" class="rounded-lg border border-slate-300 px-3 py-2">
        @endif
        <button class="rounded-lg border border-red-300 px-4 py-2 font-semibold text-red-700 hover:bg-red-50">Void sale</button>
    </form>
    <p class="mt-2 text-xs text-slate-500">Cash, M-Pesa and credit on this sale are reversed. Hand back any cash to the customer.</p>
</details>
