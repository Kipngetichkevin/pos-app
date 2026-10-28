<?php

namespace Tests\Feature;

use App\Models\CustomerTransaction;
use App\Models\Sale;
use App\Sync\SyncWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\PosTestCase;

class SyncTest extends PosTestCase
{
    public function test_newest_edit_wins(): void
    {
        $product = $this->product('Tea leaves', 100);
        $row = (array) DB::table('products')->find($product->id);

        // Older copy from another server: ignored
        $older = array_merge($row, ['selling_price' => 90, 'updated_at' => now()->subHour()->toDateTimeString()]);
        $this->assertSame(0, app(SyncWriter::class)->apply('products', [$older])['applied']);
        $this->assertSame(100.0, (float) $product->fresh()->selling_price);

        // Newer copy: applied and marked as already synced so it is not sent back
        $newer = array_merge($row, ['selling_price' => 120, 'updated_at' => now()->addMinute()->toDateTimeString()]);
        $this->assertSame(1, app(SyncWriter::class)->apply('products', [$newer])['applied']);
        $this->assertSame(120.0, (float) $product->fresh()->selling_price);
        $this->assertSame((string) $newer['updated_at'], (string) DB::table('products')->where('id', $product->id)->value('synced_at'));
    }

    public function test_receipt_sequence_never_goes_back(): void
    {
        $this->till->update(['receipt_sequence' => 50]);
        $row = array_merge((array) DB::table('tills')->find($this->till->id), [
            'name' => 'Front till',
            'receipt_sequence' => 10,
            'updated_at' => now()->addMinute()->toDateTimeString(),
        ]);

        app(SyncWriter::class)->apply('tills', [$row]);

        $this->assertSame('Front till', $this->till->fresh()->name);
        $this->assertSame(50, (int) $this->till->fresh()->receipt_sequence);
    }

    public function test_customer_balance_is_rebuilt_from_the_ledger(): void
    {
        $customer = $this->customer();
        CustomerTransaction::create(['customer_id' => $customer->id, 'type' => 'sale', 'amount' => 500, 'balance_after' => 500]);

        // A payment made at another branch arrives
        app(SyncWriter::class)->apply('customer_transactions', [[
            'id' => (string) Str::uuid(),
            'customer_id' => $customer->id,
            'type' => 'payment',
            'amount' => -200,
            'balance_after' => -200,
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]]);

        $this->assertSame(300.0, (float) $customer->fresh()->balance);
    }

    public function test_cloud_accepts_pushes_only_with_a_valid_token_and_own_branch(): void
    {
        config(['pos.mode' => 'cloud']);
        $token = 'test-token-123';
        $this->branch->forceFill(['sync_token_hash' => hash('sha256', $token)])->save();

        $this->postJson('/api/sync/push', ['table' => 'sales', 'rows' => []])->assertUnauthorized();

        $product = $this->product();
        $shift = $this->openShift($this->admin);
        $sale = fn (string $branchId) => [
            'id' => (string) Str::uuid(), 'receipt_no' => 'X-' . Str::random(6), 'branch_id' => $branchId,
            'till_id' => $this->till->id, 'shift_id' => $shift->id, 'user_id' => $this->admin->id,
            'status' => 'completed', 'subtotal' => 150, 'tax_total' => 20.69, 'grand_total' => 150, 'amount_paid' => 150,
            'sold_at' => now()->toDateTimeString(), 'created_at' => now()->toDateTimeString(), 'updated_at' => now()->toDateTimeString(),
        ];

        $this->withToken($token)->postJson('/api/sync/push', [
            'table' => 'sales',
            'rows' => [$sale($this->branch->id), $sale((string) Str::uuid())], // second claims another branch
        ])->assertOk()->assertJsonPath('applied', 1);

        $this->assertSame(1, Sale::count());

        // Pull: catalogue yes, other branches' sales never
        $this->withToken($token)->getJson('/api/sync/pull?table=products')->assertOk()->assertJsonPath('rows.0.id', $product->id);
        $this->withToken($token)->getJson('/api/sync/pull?table=sales')->assertUnprocessable();
    }

    public function test_sync_api_is_off_unless_cloud_mode(): void
    {
        $this->withToken('anything')->getJson('/api/sync/pull?table=products')->assertNotFound();
    }

    public function test_header_shows_sync_badge_on_branch_servers(): void
    {
        $this->actingAs($this->admin)->get('/')->assertOk()->assertDontSee('Sync not set up');

        config(['pos.mode' => 'branch', 'pos.cloud_url' => 'https://cloud.example', 'pos.sync_token' => 'x']);
        $this->openShift($this->admin);
        $this->actingAs($this->admin)->postJson('/pos/checkout', [
            'items' => [['product_id' => $this->product()->id, 'quantity' => 1]],
            'cash' => 150,
        ])->assertOk();

        cache()->forget('sync-status');
        $this->actingAs($this->admin)->get('/')->assertSee('Offline')->assertSee('1 sales waiting');
    }

    public function test_delivery_numbers_include_the_branch_code(): void
    {
        $product = $this->product();

        $this->actingAs($this->admin)->postJson('/stock/receive', [
            'lines' => [$product->id => ['product_id' => $product->id, 'unit_id' => '', 'quantity' => 5, 'line_total' => 600]],
        ])->assertOk();

        $this->assertSame('GRN-MAIN-' . now()->format('ymd') . '-001', \App\Models\GoodsReceipt::value('receipt_no'));
        $this->assertSame(15.0, $this->stockOf($product));
    }
}
