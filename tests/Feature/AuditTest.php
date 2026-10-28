<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Sale;
use Tests\PosTestCase;

class AuditTest extends PosTestCase
{
    public function test_price_changes_and_stock_adjustments_are_logged(): void
    {
        $product = $this->product('Cooking fat', 200, 160, 10);

        $this->actingAs($this->admin);
        $product->update(['selling_price' => 220]);

        $this->post("/stock/{$product->id}/adjust", [
            'adjustQty' => 2, 'adjustMode' => 'remove', 'adjustReason' => 'damaged',
        ])->assertSessionHasNoErrors();

        $price = AuditLog::where('action', 'price.changed')->firstOrFail();
        $this->assertSame($this->admin->id, $price->user_id);
        $this->assertStringContainsString('200.00 → 220.00', $price->description);

        $this->assertSame(1, AuditLog::where('action', 'stock.adjusted')->count());

        $this->get('/audit')->assertOk()->assertSee('Cooking fat');
        $this->get('/audit?action=stock.adjusted')->assertOk()->assertDontSee('200.00 → 220.00');
    }

    public function test_voids_below_cost_sales_and_cash_shortages_are_logged(): void
    {
        $product = $this->product('Juice', 50, 60, 10); // priced below cost
        $shift = $this->openShift($this->admin, 0);

        $this->actingAs($this->admin)->postJson('/pos/checkout', [
            'items' => [['product_id' => $product->id, 'quantity' => 1]], 'cash' => 50,
        ])->assertOk();

        $this->assertSame(1, AuditLog::where('action', 'sale.below_cost')->count());

        $sale = Sale::firstOrFail();
        $this->actingAs($this->admin)->post("/returns/{$sale->id}/void", ['reason' => 'Cashier error'])->assertSessionHasNoErrors();
        $this->assertSame(1, AuditLog::where('action', 'sale.voided')->count());

        // Sale voided, so the drawer should hold 0. 100 missing needs an explanation and is logged.
        $this->actingAs($this->admin)->post("/shifts/{$shift->id}/close", ['use_counter' => 0, 'counted' => -100]);
        $this->assertSame('open', $shift->fresh()->status);
    }

    public function test_a_cash_shortage_at_close_is_logged(): void
    {
        $shift = $this->openShift($this->admin, 1000);

        $this->actingAs($this->admin)->post("/shifts/{$shift->id}/close", ['use_counter' => 0, 'counted' => 900])
            ->assertSessionHasErrors('remarks');

        $this->actingAs($this->admin)->post("/shifts/{$shift->id}/close", ['use_counter' => 0, 'counted' => 900, 'remarks' => 'Gave change twice'])
            ->assertRedirect("/shifts/{$shift->id}/report");

        $log = AuditLog::where('action', 'shift.closed')->firstOrFail();
        $this->assertStringContainsString('SHORT by KSh 100.00', $log->description);
    }

    public function test_cashiers_cannot_see_the_audit_log(): void
    {
        $this->actingAs($this->cashier())->get('/audit')->assertForbidden();
    }
}
