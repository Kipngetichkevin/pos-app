<?php

namespace Tests\Feature;

use App\Models\Sale;
use App\Services\ReportService;
use Tests\PosTestCase;

class ReportTest extends PosTestCase
{
    public function test_profit_and_loss_add_up(): void
    {
        $soap = $this->product('Soap', 116, 80, 20);   // VAT 16%
        $this->openShift($this->admin, 0);

        // 3 x 116 = 348 sales, cost 240
        $this->actingAs($this->admin)->postJson('/pos/checkout', ['items' => [['product_id' => $soap->id, 'quantity' => 3]], 'cash' => 348])->assertOk();

        // Refund 1 (116 back, cost 80 back)
        $sale = Sale::with('items')->first();
        $this->actingAs($this->admin)->post("/returns/{$sale->id}", [
            'qty' => [$sale->items->first()->id => 1], 'reason' => 'Damaged / faulty', 'refundMethod' => 'cash',
        ])->assertSessionHasNoErrors();

        // A voided sale must not count
        $this->actingAs($this->admin)->postJson('/pos/checkout', ['items' => [['product_id' => $soap->id, 'quantity' => 5]], 'cash' => 580])->assertOk();
        $voided = Sale::latest('receipt_no')->first();
        $this->actingAs($this->admin)->post("/returns/{$voided->id}/void", ['reason' => 'Cashier error'])->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->post('/expenses', ['category' => 'meals', 'description' => 'Tea', 'amount' => 50, 'method' => 'cash']);

        $s = ReportService::for($this->branch->id, today(), today())->summary();

        $this->assertSame(1, $s['count']);
        $this->assertSame(232.0, $s['revenue']);       // 348 - 116
        $this->assertSame(160.0, $s['cost']);          // 240 - 80
        $this->assertSame(72.0, $s['gross_profit']);
        $this->assertSame(22.0, $s['net_profit']);     // 72 - 50 tea

        $vatB = ReportService::for($this->branch->id, today(), today())->vat()->firstWhere('code', 'B');
        $this->assertSame(232.0, $vatB['net_gross']);
        $this->assertSame(32.0, $vatB['net_tax']);     // 16/116 of 232

        $this->actingAs($this->admin)->get('/reports?range=today&tab=profit')->assertOk()->assertSee('232.00');
        $this->actingAs($this->admin)->get('/reports/export?range=today&tab=vat')->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_dashboard_shows_todays_numbers_and_alerts(): void
    {
        $milk = $this->product('Milk', 60, 50, 1);
        $milk->update(['reorder_level' => 5]);
        $this->openShift($this->admin);

        $this->actingAs($this->admin)->postJson('/pos/checkout', ['items' => [['product_id' => $milk->id, 'quantity' => 1]], 'cash' => 60])->assertOk();

        $this->actingAs($this->admin)->get('/')->assertOk()
            ->assertSee('KSh 60')
            ->assertSee('out of stock');
    }
}
