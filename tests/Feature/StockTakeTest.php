<?php

namespace Tests\Feature;

use App\Models\StockTake;
use Tests\PosTestCase;

class StockTakeTest extends PosTestCase
{
    public function test_count_and_complete_corrects_stock_and_values_the_variance(): void
    {
        $sugar = $this->product('Sugar 1kg', 150, 120, 10);
        $salt = $this->product('Salt 500g', 40, 25, 20);
        $oil = $this->product('Oil 1L', 300, 250, 5);

        $this->actingAs($this->admin)->post('/stock-takes')->assertRedirect();
        $take = StockTake::with('items')->firstOrFail();
        $this->assertSame(3, $take->items->count());

        // Only one stock take at a time
        $this->actingAs($this->admin)->post('/stock-takes')->assertSessionHasErrors('general');

        $item = fn ($product) => $take->items->firstWhere('product_id', $product->id)->id;

        $this->actingAs($this->admin)->get("/stock-takes/{$take->id}")->assertOk()->assertSee('Sugar 1kg');

        $this->actingAs($this->admin)->post("/stock-takes/{$take->id}/count", [
            'counts' => [$item($sugar) => 8, $item($salt) => 22, $item($oil) => ''], // oil not counted
        ])->assertSessionHasNoErrors();

        // Whole units only for pieces
        $this->actingAs($this->admin)->post("/stock-takes/{$take->id}/count", [
            'counts' => [$item($salt) => 21.5],
        ])->assertSessionHasErrors("counts.{$item($salt)}");

        $this->actingAs($this->admin)->post("/stock-takes/{$take->id}/complete")->assertRedirect();

        $take->refresh();
        $this->assertSame('completed', $take->status);
        $this->assertSame(8.0, $this->stockOf($sugar));
        $this->assertSame(22.0, $this->stockOf($salt));
        $this->assertSame(5.0, $this->stockOf($oil)); // uncounted: unchanged
        $this->assertSame(240.0, (float) $take->shortage_value); // 2 x 120
        $this->assertSame(50.0, (float) $take->excess_value);    // 2 x 25

        $this->actingAs($this->admin)->get("/stock-takes/{$take->id}")->assertOk()->assertSee('Sugar 1kg');
        $this->actingAs($this->admin)->get('/stock-takes')->assertOk()->assertSee($take->take_no);
    }

    public function test_a_sale_during_the_count_is_not_a_shortage(): void
    {
        $sugar = $this->product('Sugar 1kg', 150, 120, 10);
        $this->openShift($this->admin);

        $this->actingAs($this->admin)->post('/stock-takes');
        $take = StockTake::with('items')->firstOrFail();

        // Counted 10 on the shelf, then 3 were sold before finishing
        $this->actingAs($this->admin)->post("/stock-takes/{$take->id}/count", ['counts' => [$take->items->first()->id => 10]]);
        $this->actingAs($this->admin)->postJson('/pos/checkout', [
            'items' => [['product_id' => $sugar->id, 'quantity' => 3]], 'cash' => 450,
        ])->assertOk();

        $this->actingAs($this->admin)->post("/stock-takes/{$take->id}/complete");

        $this->assertSame(7.0, $this->stockOf($sugar));
        $this->assertSame(0.0, (float) $take->fresh()->shortage_value);
    }
}
