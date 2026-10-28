<?php

namespace App\Sync;

use App\Models\SyncState;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Branch server side of syncing. Sends what changed here and fetches what changed in the cloud.
 * Safe to run at any time: if the internet is down it fails quietly and tries again next run.
 */
class SyncClient
{
    public function __construct(private SyncWriter $writer)
    {
    }

    public static function enabled(): bool
    {
        return config('pos.mode') === 'branch' && config('pos.cloud_url') && config('pos.sync_token');
    }

    /** @return array{pushed:int, pulled:int} */
    public function run(): array
    {
        if (! self::enabled()) {
            throw new RuntimeException('Syncing is off. Set POS_MODE=branch, POS_CLOUD_URL and POS_SYNC_TOKEN in .env.');
        }

        SyncState::write('last_attempt_at', now()->toDateTimeString());

        try {
            $pushed = 0;
            $pulled = 0;

            // Send ours first, so the cloud's copy is up to date before we read it back
            foreach (SyncTables::pushable() as $table) {
                $pushed += $this->push($table);
            }

            foreach (SyncTables::pullable() as $table) {
                $pulled += $this->pull($table);
            }

            SyncState::write('last_success_at', now()->toDateTimeString());
            SyncState::write('last_error', null);

            return ['pushed' => $pushed, 'pulled' => $pulled];
        } catch (Throwable $e) {
            SyncState::write('last_error', mb_substr($e->getMessage(), 0, 500));
            throw $e;
        }
    }

    /** Send rows that are new or changed since they were last synced. */
    public function push(string $table, int $maxBatches = 50): int
    {
        $sent = 0;

        for ($i = 0; $i < $maxBatches; $i++) {
            $rows = DB::table($table)
                ->where(fn ($q) => $q->whereNull('synced_at')->orWhereColumn('updated_at', '>', 'synced_at'))
                ->orderBy('updated_at')
                ->limit(config('pos.sync_batch'))
                ->get();

            if ($rows->isEmpty()) {
                break;
            }

            $this->send($table, $rows->map(fn ($r) => (array) $r)->all());
            $sent += $rows->count();

            if ($rows->count() < config('pos.sync_batch')) {
                break;
            }
        }

        return $sent;
    }

    /** Send specific rows now (e.g. an M-Pesa prompt the cloud must know about before Safaricom calls back). */
    public function pushRows(string $table, array $ids): void
    {
        $rows = DB::table($table)->whereIn('id', $ids)->get()->map(fn ($r) => (array) $r)->all();

        if ($rows) {
            $this->send($table, $rows);
        }
    }

    protected function send(string $table, array $rows): void
    {
        $response = $this->http()->post('/api/sync/push', ['table' => $table, 'rows' => $rows]);

        if (! $response->successful()) {
            throw new RuntimeException("Cloud refused {$table} (HTTP {$response->status()}): " . mb_substr($response->body(), 0, 200));
        }

        // Mark as synced, unless the row changed again while we were sending it
        foreach ($rows as $row) {
            DB::table($table)
                ->where('id', $row['id'])
                ->when($row['updated_at'] ?? null, fn ($q, $at) => $q->where('updated_at', $at), fn ($q) => $q->whereNull('updated_at'))
                ->update(['synced_at' => $row['updated_at'] ?? now()]);
        }
    }

    /** Fetch rows the cloud changed after our cursor for this table. */
    public function pull(string $table, int $maxBatches = 50): int
    {
        $received = 0;

        for ($i = 0; $i < $maxBatches; $i++) {
            [$afterAt, $afterId] = explode('|', SyncState::read("pull:{$table}", '|')) + ['', ''];

            $response = $this->http()->get('/api/sync/pull', [
                'table' => $table,
                'after_at' => $afterAt,
                'after_id' => $afterId,
                'limit' => config('pos.sync_batch'),
            ]);

            if (! $response->successful()) {
                throw new RuntimeException("Could not fetch {$table} (HTTP {$response->status()}).");
            }

            $rows = $response->json('rows', []);

            if (! $rows) {
                break;
            }

            $this->writer->apply($table, $rows);
            $last = end($rows);
            SyncState::write("pull:{$table}", $last['updated_at'] . '|' . $last['id']);
            $received += count($rows);

            if (count($rows) < config('pos.sync_batch')) {
                break;
            }
        }

        return $received;
    }

    /** Ask the cloud for one M-Pesa payment (by our id or by M-Pesa code) and store it here. */
    public function fetchMpesa(?string $id = null, ?string $receipt = null): void
    {
        $response = $this->http(8)->get('/api/sync/mpesa', array_filter(['id' => $id, 'receipt' => $receipt]));

        if ($response->successful() && $response->json('row')) {
            $this->writer->apply('mpesa_transactions', [$response->json('row')]);
        }
    }

    protected function http(int $timeout = 30): PendingRequest
    {
        return Http::baseUrl(config('pos.cloud_url'))
            ->withToken(config('pos.sync_token'))
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout($timeout);
    }
}
