<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Till;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Sets up a fresh shop: database tables, the shop's branch and tills, and the owner's login.
 * Safe to run again: it never deletes data and skips what already exists.
 */
class InstallPos extends Command
{
    protected $signature = 'pos:install
        {--shop= : Shop name, shown on receipts}
        {--branch-code=MAIN : Short branch code used in receipt numbers (letters/digits)}
        {--phone= : Shop phone for receipts}
        {--address= : Shop address for receipts}
        {--kra-pin= : Business KRA PIN}
        {--tills=1 : Number of tills (counters)}
        {--admin-name= : Owner / admin full name}
        {--admin-username=admin : Admin username}
        {--admin-password= : Admin password (asked if left out)}
        {--admin-pin= : 4-6 digit manager PIN for approving voids and refunds}';

    protected $description = 'Set up this computer as a shop POS server (tables, branch, tills, admin login)';

    public function handle(): int
    {
        $this->components->info('Setting up the POS');

        try {
            DB::connection()->getPdo();
        } catch (Throwable $e) {
            $this->components->error('Cannot connect to the database. Check DB_* settings in .env and that MySQL is running.');
            $this->line($e->getMessage());

            return self::FAILURE;
        }

        if (! config('app.key')) {
            Artisan::call('key:generate', ['--force' => true]);
            $this->components->task('App key created');
        }

        $this->components->task('Database tables', fn () => Artisan::call('migrate', ['--force' => true]) === 0);
        $this->components->task('Units, tax rates and default category', fn () => Artisan::call('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]) === 0);

        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $this->option('branch-code')) ?: 'MAIN');
        $shop = $this->option('shop') ?: $this->ask('Shop name (shown on receipts)', config('app.name'));

        $branch = Branch::where('code', $code)->first() ?? Branch::where('code', 'MAIN')->first() ?? new Branch();
        $branch->fill(array_filter([
            'code' => $code,
            'name' => $shop,
            'phone' => $this->option('phone'),
            'address' => $this->option('address'),
            'kra_pin' => $this->option('kra-pin') ? strtoupper($this->option('kra-pin')) : null,
            'is_active' => true,
        ], fn ($v) => $v !== null && $v !== ''))->save();
        $this->components->task("Branch {$branch->name} ({$branch->code})");

        $tills = max(1, (int) $this->option('tills'));
        for ($i = 1; $i <= $tills; $i++) {
            Till::firstOrCreate(
                ['branch_id' => $branch->id, 'code' => sprintf('T%02d', $i)],
                ['name' => "Till {$i}", 'is_active' => true],
            );
        }
        $this->components->task("{$tills} till(s)");

        $username = Str::lower((string) $this->option('admin-username'));
        $admin = User::where('username', $username)->first();

        if (! $admin || $this->option('admin-password')) {
            $password = $this->option('admin-password') ?: $this->secret('Password for the admin login (at least 6 characters)');

            if (mb_strlen((string) $password) < 6) {
                $this->components->error('The password must be at least 6 characters.');

                return self::FAILURE;
            }

            $admin ??= new User(['username' => $username, 'role' => 'admin']);
            $admin->fill([
                'branch_id' => $branch->id,
                'name' => $this->option('admin-name') ?: ($admin->name ?: 'Owner'),
                'role' => 'admin',
                'is_active' => true,
            ]);
            $admin->password = $password;

            if ($pin = $this->option('admin-pin')) {
                $admin->pin = $pin;
            }

            $admin->save();
        }
        $this->components->task("Admin login: {$admin->username}");

        $this->newLine();
        $this->components->info('Done. Open ' . config('app.url') . ' and sign in as ' . $admin->username . '.');

        return self::SUCCESS;
    }
}
