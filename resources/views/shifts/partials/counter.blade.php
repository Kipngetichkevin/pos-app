{{-- Cash count: totals are worked out live in the browser; storeClose recomputes them on the server --}}
@php
    $oldNotes = [];
    foreach ($denominations as $value) {
        $oldNotes[$value] = (string) old('notes.' . $value, '');
    }
@endphp
<form method="POST" action="{{ route('shifts.close.store', $shift) }}"
      onsubmit="return confirm('Close this shift? No more sales can be made on it.')"
      class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200"
      x-data="{
          useCounter: @js(old('use_counter', '1') === '1'),
          notes: @js($oldNotes),
          counted: @js((string) old('counted', '')),
          expected: @js((float) $summary['expected_cash']),
          get total() {
              if (this.useCounter) {
                  return Object.entries(this.notes).reduce((sum, [value, qty]) => sum + (parseInt(qty) || 0) * Number(value), 0);
              }
              return Math.round((parseFloat(this.counted) || 0) * 100) / 100;
          },
          get variance() { return Math.round((this.total - this.expected) * 100) / 100; },
      }">
    @csrf
    <input type="hidden" name="use_counter" :value="useCounter ? '1' : '0'" value="{{ old('use_counter', '1') }}">

    <div class="mb-3 flex items-center justify-between">
        <h3 class="font-semibold">Count the cash in the drawer</h3>
        <button type="button" x-on:click="useCounter = ! useCounter" class="text-xs text-blue-600 hover:underline"
                x-text="useCounter ? 'Enter total instead' : 'Count notes and coins'">Enter total instead</button>
    </div>

    <div x-show="useCounter" class="space-y-1.5">
        @foreach ($denominations as $value)
            <div class="flex items-center gap-2 text-sm">
                <span class="w-16 text-right font-semibold">{{ number_format($value) }}</span>
                <span class="text-slate-400">&times;</span>
                <input name="notes[{{ $value }}]" x-model="notes['{{ $value }}']" type="number" min="0" step="1" placeholder="0"
                       class="w-20 rounded border border-slate-300 px-2 py-1 text-center">
                <span class="ml-auto text-slate-600" x-text="((parseInt(notes['{{ $value }}']) || 0) * {{ $value }}).toLocaleString('en-US')">0</span>
            </div>
        @endforeach
    </div>
    <div x-show="! useCounter" x-cloak>
        <input name="counted" x-model="counted" type="number" step="0.01" min="0" placeholder="Total cash counted"
               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-lg">
    </div>
    @include('partials.error', ['field' => 'counted'])

    <div class="mt-4 space-y-1 border-t border-slate-200 pt-3 text-sm">
        <div class="flex justify-between"><span>Counted</span><span class="font-semibold" x-text="money(total)">0.00</span></div>
        <div class="flex justify-between"><span>Expected</span><span>{{ number_format($summary['expected_cash'], 2) }}</span></div>
        <div class="flex justify-between rounded-lg p-2 text-base font-bold"
             :class="variance == 0 ? 'bg-green-50 text-green-800' : (variance < 0 ? 'bg-red-50 text-red-700' : 'bg-amber-50 text-amber-800')">
            <span x-text="variance == 0 ? 'Balanced' : (variance < 0 ? 'Shortage' : 'Excess')"></span>
            <span x-text="money(Math.abs(variance))"></span>
        </div>
    </div>

    <textarea name="remarks" rows="2" placeholder="Remarks (required if there is a difference)"
              class="mt-3 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('remarks') }}</textarea>
    @include('partials.error', ['field' => 'remarks'])

    <button type="submit" class="mt-3 w-full rounded-xl bg-red-600 py-3 font-bold text-white hover:bg-red-700">
        Close shift
    </button>
</form>
