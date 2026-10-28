<?php

namespace Tests;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Shift;
use App\Models\TaxRate;
use App\Models\Till;
use App\Models\Unit;
use App\Models\User;
use App\Services\StockService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/** Seeded shop (branch MAIN, till T01, admin) with helpers for products, staff and shifts. */
abstract class PosTestCase extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch;

    protected Till $till;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->branch = Branch::where('code', 'MAIN')->firstOrFail();
        $this->till = Till::where('code', 'T01')->firstOrFail();
        $this->admin = User::where('username', 'admin')->firstOrFail();
    }

    protected function cashier(): User
    {
        return User::create([
            'branch_id' => $this->branch->id,
            'name' => 'Jane Cashier',
            'username' => 'jane',
            'password' => 'secret123',
            'role' => 'cashier',
            'is_active' => true,
        ]);
    }

    protected function product(string $name = 'Sugar 1kg', float $price = 150, float $cost = 120, float $stock = 10): Product
    {
        $product = Product::create([
            'name' => $name,
            'sku' => 'SKU-' . str()->random(6),
            'unit_id' => Unit::where('short_name', 'pc')->value('id'),
            'tax_rate_id' => TaxRate::where('code', 'B')->value('id'),
            'cost_price' => $cost,
            'selling_price' => $price,
            'track_stock' => true,
            'is_active' => true,
        ]);

        if ($stock > 0) {
            app(StockService::class)->move($product, $this->branch->id, $stock, 'opening', $this->admin->id, $cost);
        }

        return $product;
    }

    protected function openShift(User $user, float $float = 1000): Shift
    {
        return Shift::create([
            'branch_id' => $this->branch->id,
            'till_id' => $this->till->id,
            'user_id' => $user->id,
            'status' => 'open',
            'opened_at' => now(),
            'opening_float' => $float,
        ]);
    }

    protected function customer(float $limit = 1000): Customer
    {
        return Customer::create(['name' => 'Mama Njeri', 'phone' => '0712000111', 'credit_limit' => $limit, 'balance' => 0]);
    }

    protected function stockOf(Product $product): float
    {
        return (float) $product->stockLevels()->where('branch_id', $this->branch->id)->value('quantity');
    }
}
