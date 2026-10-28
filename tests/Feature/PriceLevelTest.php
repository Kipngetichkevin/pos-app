<?php

namespace Tests\Feature;

use App\Models\Sale;
use Tests\PosTestCase;

class PriceLevelTest extends PosTestCase
{
    public function test_wholesale_price_from_a_quantity(): void
    {
        $soap = $this->product('Bar soap', 100, 70, 50);
        $soap->update(['wholesale_price' => 90, 'wholesale_min_qty' => 6]);
        $this->openShift($this->admin);

        $this->actingAs($this->admin)->getJson("/pos/products/{$soap->id}")
            ->assertJsonPath('choices.0.item.wholesale_price', 90)
            ->assertJsonPath('choices.0.item.wholesale_min_qty', 6);

        // 5 pieces: retail
        $this->actingAs($this->admin)->postJson('/pos/checkout', ['items' => [['product_id' => $soap->id, 'quantity' => 5]], 'cash' => 500])->assertOk();
        // 6 pieces: wholesale (540, not 600)
        $this->actingAs($this->admin)->postJson('/pos/checkout', ['items' => [['product_id' => $soap->id, 'quantity' => 6]], 'cash' => 540])->assertOk();

        $this->assertSame([500.0, 540.0], Sale::orderBy('receipt_no')->pluck('grand_total')->map(fn ($v) => (float) $v)->all());
    }

    public function test_wholesale_customers_always_get_wholesale_but_not_on_packagings(): void
    {
        $oil = $this->product('Oil 1L', 300, 250, 48);
        $oil->update(['wholesale_price' => 280]); // no minimum: wholesale customers only
        $oil->packagings()->create(['name' => 'Carton', 'quantity' => 12, 'selling_price' => 3300, 'is_active' => true]);
        $carton = $oil->packagings()->first();

        $shop = $this->customer();
        $shop->update(['price_level' => 'wholesale']);
        $this->openShift($this->admin);

        $this->actingAs($this->admin)->postJson('/pos/checkout', [
            'items' => [['product_id' => $oil->id, 'quantity' => 2], ['product_id' => $oil->id, 'unit_id' => $carton->id, 'quantity' => 1]],
            'customer_id' => $shop->id,
            'cash' => 560 + 3300,
        ])->assertOk();

        $this->assertSame(3860.0, (float) Sale::firstOrFail()->grand_total);

        // A walk-in buying 2 pays retail
        $this->actingAs($this->admin)->postJson('/pos/checkout', ['items' => [['product_id' => $oil->id, 'quantity' => 2]], 'cash' => 560])
            ->assertUnprocessable();
    }

    public function test_product_form_saves_wholesale_and_rejects_above_retail(): void
    {
        $product = $this->product('Flour 2kg', 200, 160, 0);
        $base = [
            'name' => 'Flour 2kg', 'unit_id' => $product->unit_id, 'tax_rate_id' => $product->tax_rate_id,
            'cost_price' => 160, 'selling_price' => 200, 'track_stock' => 1,
        ];

        $this->actingAs($this->admin)->put("/products/{$product->id}", $base + ['wholesale_price' => 250])->assertSessionHasErrors('wholesale_price');
        $this->actingAs($this->admin)->put("/products/{$product->id}", $base + ['wholesale_price' => 185, 'wholesale_min_qty' => 10])->assertSessionHasNoErrors();

        $this->assertSame(185.0, (float) $product->fresh()->wholesale_price);
        $this->assertSame(10.0, (float) $product->fresh()->wholesale_min_qty);
    }
}
