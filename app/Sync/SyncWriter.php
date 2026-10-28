<?php

namespace App\Sync;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Applies rows received from another server (cloud or branch).
 * A row is written only if it is newer than what we already have (last write wins on updated_at).
 */
class SyncWriter
{
    protected array $columns = [];

    /** @return array{applied:int, skipped:int} */
    public function apply(string $table, array $rows): array
    {
        if (! isset(SyncTables::TABLES[$table]) || ! $rows) {
            return ['applied' => 0, 'skipped' => count($rows)];
        }

        $columns = $this->columns($table);
        $exclude = array_merge(['synced_at'], SyncTables::LOCAL_ONLY[$table] ?? []);

        $rows = collect($rows)
            ->filter(fn ($r) => is_array($r) && ! empty($r['id']))
            ->keyBy('id');

        $existing = DB::table($table)->whereIn('id', $rows->keys())->get()->keyBy('id');
        $applied = 0;

        Schema::disableForeignKeyConstraints();

        try {
            DB::transaction(function () use ($table, $rows, $existing, $columns, $exclude, &$applied) {
                foreach ($rows as $id => $row) {
                    $current = $existing->get($id);

                    if ($current && (string) $current->updated_at >= (string) ($row['updated_at'] ?? '')) {
                        continue; // ours is the same or newer
                    }

                    $data = array_intersect_key($row, array_flip($columns));
                    $data = array_diff_key($data, array_flip($exclude));
                    $data = $this->merge($table, $data, $current);
                    $data['synced_at'] = $row['updated_at'] ?? now()->toDateTimeString();

                    $current
                        ? DB::table($table)->where('id', $id)->update($data)
                        : DB::table($table)->insert($data);

                    $applied++;
                }

                if ($table === 'customer_transactions' || $table === 'customers') {
                    $this->recalculateBalances($table === 'customers'
                        ? $rows->keys()->all()
                        : $rows->pluck('customer_id')->filter()->unique()->all());
                }
            });
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        return ['applied' => $applied, 'skipped' => $rows->count() - $applied];
    }

    /** Values that must never go backwards when another server's copy arrives. */
    protected function merge(string $table, array $data, ?object $current): array
    {
        // Receipt numbers come from the till's sequence: keep the highest one ever used
        if ($table === 'tills' && $current && isset($data['receipt_sequence'])) {
            $data['receipt_sequence'] = max((int) $current->receipt_sequence, (int) $data['receipt_sequence']);
        }

        // A customer's balance is the sum of their transactions, worked out locally
        if ($table === 'customers' && $current) {
            unset($data['balance']);
        }

        return $data;
    }

    /** Debt is a ledger: recompute from transactions so payments made at different places add up. */
    public function recalculateBalances(array $customerIds): void
    {
        foreach (array_chunk($customerIds, 200) as $ids) {
            $sums = DB::table('customer_transactions')
                ->whereIn('customer_id', $ids)
                ->groupBy('customer_id')
                ->selectRaw('customer_id, SUM(amount) as total')
                ->pluck('total', 'customer_id');

            foreach ($ids as $id) {
                DB::table('customers')->where('id', $id)->update(['balance' => round((float) ($sums[$id] ?? 0), 2)]);
            }
        }
    }

    protected function columns(string $table): array
    {
        return $this->columns[$table] ??= Schema::getColumnListing($table);
    }
}
