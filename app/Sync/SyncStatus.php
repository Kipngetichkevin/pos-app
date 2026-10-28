<?php

namespace App\Sync;

use App\Models\SyncState;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** What the header badge shows: is this branch server keeping up with the cloud? */
class SyncStatus
{
    public static function summary(): ?array
    {
        if (config('pos.mode') !== 'branch') {
            return null;
        }

        return Cache::remember('sync-status', 30, function () {
            $lastSuccess = SyncState::read('last_success_at');
            $lastSuccess = $lastSuccess ? Carbon::parse($lastSuccess) : null;

            $pendingSales = DB::table('sales')
                ->where(fn ($q) => $q->whereNull('synced_at')->orWhereColumn('updated_at', '>', 'synced_at'))
                ->count();

            // Healthy if the last good sync was within 3 schedule runs (5 minutes each)
            $state = ! SyncClient::enabled() ? 'off'
                : ($lastSuccess && $lastSuccess->gt(now()->subMinutes(15)) ? 'ok' : 'offline');

            return [
                'state' => $state,
                'last_success' => $lastSuccess,
                'last_error' => SyncState::read('last_error'),
                'pending_sales' => $pendingSales,
            ];
        });
    }
}
