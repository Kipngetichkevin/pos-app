<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\StockTransfer;
use App\Models\User;
use Tests\PosTestCase;

class TransferTest extends PosTestCase
{
    protected Branch $town;

    protected User $townManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->town = Branch::create(['code' => 'TOWN', 'name' => 'Town Branch']);
        $this->townManager = User::create([
            'branch_id' => $this->town->id, 'name' => 'Town Manager', 'username' => 'townmgr',
            'password' => 'secret123', 'role' => 'manager', 'is_active' => true,
        ]);
    }

    protected function townStock($product): float
    {
        return (float) $product->stockLevels()->where('branch_id', $this->town->id)->value('quantity');
    }

    public function test_send_then_receive_with_a_loss_in_transit(): void
    {
        $soda = $this->product('Soda 500ml', 60, 40, 48);
        $soda->packagings()->create(['name' => 'Crate', 'quantity' => 24, 'is_active' => true]);
        $crate = $soda->packagings()->first();

        $this->actingAs($this->admin)->get('/transfers/create')->assertOk()->assertSee('Town Branch');

        // Too much
        $this->actingAs($this->admin)->postJson('/transfers', [
            'to_branch_id' => $this->town->id,
            'lines' => [$soda->id => ['product_id' => $soda->id, 'unit_id' => $crate->id, 'quantity' => 3]],
        ])->assertUnprocessable()->assertJsonValidationErrors("lines.{$soda->id}.quantity");

        // 1 crate = 24 bottles leaves Main now
        $this->actingAs($this->admin)->postJson('/transfers', [
            'to_branch_id' => $this->town->id,
            'lines' => [$soda->id => ['product_id' => $soda->id, 'unit_id' => $crate->id, 'quantity' => 1]],
        ])->assertOk();

        $transfer = StockTransfer::with('items')->firstOrFail();
        $this->assertSame(24.0, $this->stockOf($soda));
        $this->assertSame(0.0, $this->townStock($soda));
        $this->assertSame(960.0, (float) $transfer->total_cost);

        // Only the receiving branch can confirm
        $this->actingAs($this->admin)->post("/transfers/{$transfer->id}/receive", ['received' => []])->assertNotFound();
        $this->actingAs($this->townManager)->get('/transfers')->assertOk()->assertSee('Confirm arrival');

        // 2 bottles broke on the way
        $this->actingAs($this->townManager)->post("/transfers/{$transfer->id}/receive", [
            'received' => [$transfer->items->first()->id => 22],
        ])->assertSessionHasNoErrors();

        $this->assertSame('received', $transfer->fresh()->status);
        $this->assertSame(22.0, $this->townStock($soda));
        $this->assertSame(2.0, $transfer->items->first()->fresh()->shortfall());
    }

    public function test_sender_can_cancel_before_arrival(): void
    {
        $rice = $this->product('Rice 1kg', 180, 140, 10);

        $this->actingAs($this->admin)->postJson('/transfers', [
            'to_branch_id' => $this->town->id,
            'lines' => [$rice->id => ['product_id' => $rice->id, 'quantity' => 4]],
        ])->assertOk();

        $transfer = StockTransfer::firstOrFail();
        $this->assertSame(6.0, $this->stockOf($rice));

        $this->actingAs($this->admin)->post("/transfers/{$transfer->id}/cancel")->assertSessionHasNoErrors();

        $this->assertSame('cancelled', $transfer->fresh()->status);
        $this->assertSame(10.0, $this->stockOf($rice));
    }

    public function test_cloud_sends_transfers_only_to_the_branches_involved(): void
    {
        config(['pos.mode' => 'cloud']);
        $this->town->forceFill(['sync_token_hash' => hash('sha256', 'town-token')])->save();
        $other = Branch::create(['code' => 'FAR', 'name' => 'Far Branch', 'sync_token_hash' => hash('sha256', 'far-token')]);

        $rice = $this->product('Rice 1kg', 180, 140, 10);
        $this->actingAs($this->admin)->postJson('/transfers', [
            'to_branch_id' => $this->town->id,
            'lines' => [$rice->id => ['product_id' => $rice->id, 'quantity' => 2]],
        ])->assertOk();

        $this->withToken('town-token')->getJson('/api/sync/pull?table=stock_transfers')->assertJsonCount(1, 'rows');
        $this->withToken('town-token')->getJson('/api/sync/pull?table=stock_transfer_items')->assertJsonCount(1, 'rows');
        $this->withToken('far-token')->getJson('/api/sync/pull?table=stock_transfers')->assertJsonCount(0, 'rows');
        $this->withToken('far-token')->getJson('/api/sync/pull?table=stock_transfer_items')->assertJsonCount(0, 'rows');
    }
}
