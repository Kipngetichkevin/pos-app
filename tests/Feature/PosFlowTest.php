<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Sale;
use App\Models\Shift;
use Tests\PosTestCase;

class PosFlowTest extends PosTestCase
{
    public function test_opening_a_shift(): void
    {
        $this->actingAs($this->admin)
            ->post('/pos/shift', ['till_id' => $this->till->id, 'opening_float' => 500])
            ->assertRedirect('/pos');

        $this->assertSame(1, Shift::where('status', 'open')->count());

        // The same till cannot have two open shifts
        $this->actingAs($this->cashier())
            ->post('/pos/shift', ['till_id' => $this->till->id, 'opening_float' => 0])
            ->assertSessionHasErrors('till_id');
    }

    public function test_scan_lookup_and_cash_sale(): void
    {
        $product = $this->product('Milk 500ml', 60, 45, 20);
        $product->barcodes()->create(['barcode' => '6161100000012', 'pack_quantity' => 1]);
        $this->openShift($this->admin);

        $this->actingAs($this->admin)->getJson('/pos/lookup?q=6161100000012')
            ->assertOk()
            ->assertJsonPath('add.product_id', $product->id)
            ->assertJsonPath('add.price', 60);

        $this->actingAs($this->admin)->getJson('/pos/lookup?q=nothing-like-this')->assertUnprocessable();

        $response = $this->actingAs($this->admin)->postJson('/pos/checkout', [
            'items' => [['product_id' => $product->id, 'unit_id' => null, 'quantity' => 3]],
            'cash' => 200,
        ])->assertOk();

        $this->assertEquals(20, $response->json('change'));
        $this->assertSame(17.0, $this->stockOf($product));

        $sale = Sale::firstOrFail();
        $this->assertSame('MAIN-T01-000001', $sale->receipt_no);
        $this->assertSame(180.0, (float) $sale->grand_total);

        $this->actingAs($this->admin)->get("/receipts/{$sale->id}")->assertOk()->assertSee('MAIN-T01-000001');
    }

    public function test_underpayment_is_rejected(): void
    {
        $product = $this->product();
        $this->openShift($this->admin);

        $this->actingAs($this->admin)->postJson('/pos/checkout', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'cash' => 100,
        ])->assertUnprocessable()->assertJsonValidationErrors('checkout');

        $this->assertSame(0, Sale::count());
    }

    public function test_credit_sale_respects_the_limit_for_cashiers(): void
    {
        $product = $this->product('Rice 2kg', 300, 250, 10);
        $customer = $this->customer(500);
        $cashier = $this->cashier();
        $this->openShift($cashier);

        $sale = fn (int $qty, float $credit) => $this->actingAs($cashier)->postJson('/pos/checkout', [
            'items' => [['product_id' => $product->id, 'quantity' => $qty]],
            'customer_id' => $customer->id,
            'credit' => $credit,
        ]);

        $sale(1, 300)->assertOk();
        $this->assertSame(300.0, (float) $customer->fresh()->balance);

        // 300 + 600 would be over the 500 limit
        $sale(2, 600)->assertUnprocessable()->assertJsonValidationErrors('checkout');
        $this->assertSame(300.0, (float) $customer->fresh()->balance);
    }

    public function test_partial_return_with_restock_and_cash_refund(): void
    {
        $product = $this->product('Soap', 100, 70, 10);
        $this->openShift($this->admin);

        $this->actingAs($this->admin)->postJson('/pos/checkout', [
            'items' => [['product_id' => $product->id, 'quantity' => 4]],
            'cash' => 400,
        ])->assertOk();

        $sale = Sale::with('items')->firstOrFail();
        $item = $sale->items->first();

        $this->actingAs($this->admin)->post("/returns/{$sale->id}", [
            'qty' => [$item->id => 1],
            'restock' => [$item->id => 1],
            'reason' => 'Damaged / faulty',
            'refundMethod' => 'cash',
        ])->assertSessionHasNoErrors();

        $this->assertSame('partially_refunded', $sale->fresh()->status);
        $this->assertSame(7.0, $this->stockOf($product));
        $this->assertSame(100.0, (float) $sale->returns()->first()->total_refunded);

        // Cannot return more than is left
        $this->actingAs($this->admin)->post("/returns/{$sale->id}", [
            'qty' => [$item->id => 5],
            'reason' => 'Wrong item',
            'refundMethod' => 'cash',
        ])->assertSessionHasErrors("qty.{$item->id}");

        $shift = Shift::firstOrFail();
        $this->assertSame(1000 + 400 - 100.0, $shift->summary()['expected_cash']);
    }

    public function test_cashier_needs_a_manager_pin_and_void_reverses_credit(): void
    {
        $product = $this->product('Bread', 65, 50, 10);
        $customer = $this->customer(1000);
        $cashier = $this->cashier();
        $this->openShift($cashier);

        $this->actingAs($cashier)->postJson('/pos/checkout', [
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'customer_id' => $customer->id,
            'credit' => 130,
        ])->assertOk();

        $sale = Sale::firstOrFail();

        $this->actingAs($cashier)->post("/returns/{$sale->id}/void", ['reason' => 'Cashier error'])->assertSessionHasErrors('pin');
        $this->actingAs($cashier)->post("/returns/{$sale->id}/void", ['reason' => 'Cashier error', 'pin' => '9999'])->assertSessionHasErrors('pin');
        $this->actingAs($cashier)->post("/returns/{$sale->id}/void", ['reason' => 'Cashier error', 'pin' => '1234'])->assertSessionHasNoErrors();

        $this->assertSame('voided', $sale->fresh()->status);
        $this->assertSame($this->admin->id, $sale->fresh()->voided_by);
        $this->assertSame(10.0, $this->stockOf($product));
        $this->assertSame(0.0, (float) Customer::find($customer->id)->balance);
    }
}
