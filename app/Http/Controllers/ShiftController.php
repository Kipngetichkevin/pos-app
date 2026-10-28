<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    public const DENOMINATIONS = [1000, 500, 200, 100, 50, 20, 10, 5, 1];

    public function index()
    {
        // Managers only: enforced by the 'manager' route middleware
        $shifts = Shift::with(['till', 'user'])
            ->withSum(['sales as sales_total' => fn ($q) => $q->where('status', '!=', 'voided')], 'grand_total')
            ->latest('opened_at')
            ->paginate(25);

        return view('shifts.index', ['shifts' => $shifts]);
    }

    public function close(Shift $shift)
    {
        $this->authorizeShift($shift);

        if ($shift->status !== 'open') {
            return redirect()->route('shifts.report', $shift);
        }

        $shift->load(['till', 'user']);

        return view('shifts.close', [
            'shift' => $shift,
            'summary' => $shift->summary(),
            'denominations' => self::DENOMINATIONS,
        ]);
    }

    public function storeClose(Request $request, Shift $shift)
    {
        $this->authorizeShift($shift);

        $shift->refresh();

        if ($shift->status !== 'open') {
            return redirect()->route('shifts.report', $shift);
        }

        $counted = round($this->countedTotal($request), 2);
        $summary = $shift->summary();
        $variance = round($counted - $summary['expected_cash'], 2);
        $remarks = trim((string) $request->input('remarks'));

        if ($counted < 0) {
            return back()->withInput()->withErrors(['counted' => 'Counted cash cannot be negative.']);
        }

        if ($variance != 0 && $remarks === '') {
            return back()->withInput()->withErrors(['remarks' => 'There is a difference of KSh ' . number_format(abs($variance), 2) . '. Please explain it before closing.']);
        }

        $shift->update([
            'status' => 'closed',
            'closed_at' => now(),
            'expected_cash' => $summary['expected_cash'],
            'counted_cash' => $counted,
            'variance' => $variance,
            'closed_by' => auth()->id(),
            'notes' => $remarks ?: null,
        ]);

        return redirect()->route('shifts.report', $shift);
    }

    public function report(Shift $shift)
    {
        $this->authorizeShift($shift);

        $shift->load(['till', 'user', 'closedBy', 'branch']);

        return view('shifts.report', ['shift' => $shift, 'summary' => $shift->summary()]);
    }

    /**
     * The cashier who owns the shift, or a manager.
     */
    protected function authorizeShift(Shift $shift): void
    {
        $user = auth()->user();
        abort_unless($shift->user_id === $user->id || $user->isManager(), 403);
    }

    /**
     * Same as the old Close::countedTotal(): note counts x value, or the typed total.
     */
    protected function countedTotal(Request $request): float
    {
        if ($request->boolean('use_counter')) {
            $notes = (array) $request->input('notes', []);

            return (float) collect(self::DENOMINATIONS)->map(fn ($value) => (int) ($notes[$value] ?? 0) * $value)->sum();
        }

        return round((float) $request->input('counted'), 2);
    }
}
