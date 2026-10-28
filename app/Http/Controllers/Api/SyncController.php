<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Sync\SyncTables;
use App\Sync\SyncWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Cloud side of syncing. Branch servers push their changes here and pull everyone else's. */
class SyncController extends Controller
{
    /** Tables whose rows carry the branch that owns them. A branch may only send its own rows. */
    protected const OWNED_BY_BRANCH = [
        'shifts', 'sales', 'sale_returns', 'expenses', 'stock_takes', 'audit_logs', 'etims_invoices', 'goods_receipts', 'repacks', 'stock_batches', 'stock_levels', 'stock_movements',
    ];

    public function push(Request $request, SyncWriter $writer)
    {
        $data = $request->validate([
            'table' => 'required|string',
            'rows' => 'present|array|max:1000',
        ]);

        $table = $data['table'];
        abort_unless(SyncTables::canPush($table), 422, "Table {$table} cannot be pushed.");

        /** @var Branch $branch */
        $branch = $request->attributes->get('branch');
        $rows = $data['rows'];

        if (in_array($table, self::OWNED_BY_BRANCH, true)) {
            $rows = array_values(array_filter($rows, fn ($r) => ($r['branch_id'] ?? null) === $branch->id));
        }

        if ($table === 'stock_transfers') {
            $rows = array_values(array_filter($rows, fn ($r) => in_array($branch->id, [$r['from_branch_id'] ?? null, $r['to_branch_id'] ?? null], true)));
        }

        $result = $writer->apply($table, $rows);
        $branch->forceFill(['last_sync_at' => now()])->saveQuietly();

        return response()->json($result);
    }

    public function pull(Request $request)
    {
        $data = $request->validate([
            'table' => 'required|string',
            'after_at' => 'nullable|string',
            'after_id' => 'nullable|string',
            'limit' => 'nullable|integer|min:1|max:1000',
        ]);

        $table = $data['table'];
        abort_unless(SyncTables::canPull($table), 422, "Table {$table} cannot be pulled.");

        $branch = $request->attributes->get('branch');
        $afterAt = $data['after_at'] ?? '';
        $afterId = $data['after_id'] ?? '';

        $rows = DB::table($table)
            ->when($afterAt !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('updated_at', '>', $afterAt)
                ->orWhere(fn ($q) => $q->where('updated_at', $afterAt)->where('id', '>', $afterId))))
            ->when($afterAt === '', fn ($q) => $q->whereNotNull('updated_at'))
            // Payments for other branches' tills stay with them; paybill/till payments without a branch go to all
            ->when($table === 'mpesa_transactions', fn ($q) => $q->where(fn ($q) => $q->whereNull('branch_id')->orWhere('branch_id', $branch->id)))
            // Transfers go only to the two branches involved
            ->when($table === 'stock_transfers', fn ($q) => $q->where(fn ($q) => $q->where('from_branch_id', $branch->id)->orWhere('to_branch_id', $branch->id)))
            ->when($table === 'stock_transfer_items', fn ($q) => $q->whereIn('stock_transfer_id', DB::table('stock_transfers')->select('id')
                ->where(fn ($q) => $q->where('from_branch_id', $branch->id)->orWhere('to_branch_id', $branch->id))))
            ->orderBy('updated_at')
            ->orderBy('id')
            ->limit($data['limit'] ?? 200)
            ->get()
            ->map(fn ($r) => array_diff_key((array) $r, array_flip(SyncTables::LOCAL_ONLY[$table] ?? [])));

        return response()->json(['rows' => $rows->values()]);
    }

    /** One M-Pesa payment, for a branch waiting on an STK prompt or checking a code typed at the till. */
    public function mpesa(Request $request)
    {
        $branch = $request->attributes->get('branch');

        $row = DB::table('mpesa_transactions')
            ->when($request->query('id'), fn ($q, $id) => $q->where('id', $id))
            ->when($request->query('receipt'), fn ($q, $r) => $q->where('mpesa_receipt', strtoupper($r)))
            ->when(! $request->query('id') && ! $request->query('receipt'), fn ($q) => $q->whereRaw('1 = 0'))
            ->where(fn ($q) => $q->whereNull('branch_id')->orWhere('branch_id', $branch->id))
            ->first();

        return response()->json(['row' => $row ? (array) $row : null]);
    }
}
