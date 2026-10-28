<?php

use App\Models\Branch;
use App\Services\MpesaService;
use App\Sync\SyncClient;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('mpesa:register-c2b', function (MpesaService $mpesa) {
    $confirmation = config('mpesa.c2b_confirmation_url');
    $validation = config('mpesa.c2b_validation_url');

    foreach ([$confirmation, $validation] as $url) {
        if (! $url || ! str_starts_with($url, 'https://')) {
            $this->error("Missing or non-https URL: {$url}");
            return 1;
        }
        if (preg_match('/mpesa|m-pesa|safaricom/i', $url)) {
            $this->error("Safaricom rejects URLs containing 'mpesa' or 'safaricom': {$url}");
            return 1;
        }
    }

    $this->info('Shortcode:    ' . config('mpesa.shortcode') . ' (' . config('mpesa.env') . ')');
    $this->info('Confirmation: ' . $confirmation);
    $this->info('Validation:   ' . $validation);

    if (! $this->confirm('This REPLACES any C2B URLs already registered for this shortcode. Continue?')) {
        $this->line('Cancelled.');
        return 0;
    }

    $this->line(json_encode($mpesa->registerC2bUrls(), JSON_PRETTY_PRINT));
})->purpose('Register C2B confirmation and validation URLs with Safaricom');

Artisan::command('pos:sync', function (SyncClient $sync) {
    if (! SyncClient::enabled()) {
        $this->error('Syncing is off. Set POS_MODE=branch, POS_CLOUD_URL and POS_SYNC_TOKEN in .env.');
        return 1;
    }

    $this->info('Pushing local changes and pulling the cloud...');

    try {
        $result = $sync->run();
        $this->info("Pushed {$result['pushed']} row(s), pulled {$result['pulled']} row(s).");
        return 0;
    } catch (\Throwable $e) {
        $this->error('Sync failed: ' . $e->getMessage());
        return 1;
    }
})->purpose('Send this branch sales/stock to the cloud and fetch catalogue/customer changes');

Artisan::command('pos:branch-token {code : Branch code, e.g. NRB01}', function (string $code) {
    if (config('pos.mode') !== 'cloud') {
        $this->error('This command only runs on the cloud server (POS_MODE=cloud).');
        return 1;
    }

    $branch = Branch::where('code', $code)->first();

    if (! $branch) {
        $this->error("No branch with code {$code}.");
        return 1;
    }

    $token = Str::random(64);
    $branch->forceFill(['sync_token_hash' => hash('sha256', $token)])->save();

    $this->info("Branch: {$branch->name} ({$branch->code})");
    $this->line('');
    $this->line('Add these lines to the BRANCH server .env:');
    $this->line('');
    $this->line('POS_MODE=branch');
    $this->line('POS_CLOUD_URL=' . rtrim((string) config('app.url'), '/'));
    $this->line('POS_BRANCH_ID=' . $branch->id);
    $this->line("POS_SYNC_TOKEN={$token}");
    $this->line('');
    $this->warn('This token is shown only once. Store it in the branch .env now.');
})->purpose('Issue a sync token for a branch (cloud only)');

Schedule::command('pos:sync')->everyFiveMinutes()->withoutOverlapping();

Artisan::command('etims:send', function (\App\Services\EtimsService $etims) {
    if (! \App\Services\EtimsService::enabled()) {
        $this->line('eTIMS is off (ETIMS_DRIVER=off).');
        return 0;
    }

    $done = $etims->sendDue();
    $this->info("eTIMS: {$done['sent']} sent, {$done['failed']} failed (will retry).");
})->purpose('Send queued and retry failed KRA eTIMS invoices');

Schedule::command('etims:send')->everyMinute()->withoutOverlapping(10);

// Nightly database backup on every shop server (kept 14 days)
Schedule::command('pos:backup')->dailyAt('22:30')->withoutOverlapping();
