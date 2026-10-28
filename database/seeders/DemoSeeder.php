<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Shift;
use App\Models\TaxRate;
use App\Models\Till;
use App\Models\Unit;
use App\Models\User;
use App\Services\SaleService;
use App\Services\StockService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A believable Kenyan mini-market with 30 days of trading, for demos, training and screenshots.
 * NEVER run on a live shop database: `php artisan db:seed --class=DemoSeeder` on a separate database only.
 */
class DemoSeeder extends Seeder
{
    public function run(SaleService $sales, StockService $stock): void
    {
        $this->call(DatabaseSeeder::class);

        mt_srand(2026);
        Carbon::setTestNow();
        $today = now()->startOfDay(); // fixed before the clock is moved around below
        $branch = Branch::where('code', 'MAIN')->first();
        $branch->update(['name' => 'Kahawa Mini Mart', 'phone' => '0722 000 111', 'address' => 'Kahawa West, Nairobi', 'kra_pin' => 'P051234567X']);
        $till = Till::where('branch_id', $branch->id)->first();
        $admin = User::where('username', 'admin')->first();
        $admin->update(['name' => 'Grace Wanjiku']);
        $cashier = User::firstOrCreate(['username' => 'brian'], [
            'branch_id' => $branch->id, 'name' => 'Brian Otieno', 'password' => 'secret123', 'pin' => '4321', 'role' => 'cashier', 'is_active' => true,
        ]);

        $pc = Unit::where('short_name', 'pc')->value('id');
        $kg = Unit::where('short_name', 'kg')->value('id');
        $vat = TaxRate::where('code', 'B')->value('id');
        $exempt = TaxRate::where('code', 'A')->value('id');

        // [category, name, cost, price, tax, unit, opening stock, popularity]
        $catalogue = [
            ['Flour & Grains', 'Jogoo Maize Meal 2kg', 158, 185, $exempt, $pc, 120, 10],
            ['Flour & Grains', 'Exe Home Baking Flour 2kg', 172, 199, $exempt, $pc, 80, 6],
            ['Flour & Grains', 'Pishori Rice 1kg', 185, 220, $vat, $pc, 60, 5],
            ['Flour & Grains', 'Sugar (loose)', 140, 165, $vat, $kg, 150, 9],
            ['Dairy', 'Brookside Milk 500ml', 52, 60, $exempt, $pc, 200, 12],
            ['Dairy', 'Tuzo Fresh Milk 1L', 105, 120, $exempt, $pc, 80, 6],
            ['Dairy', 'Daima Yoghurt 500ml', 95, 115, $vat, $pc, 40, 3],
            ['Bakery', 'Superloaf White Bread 400g', 58, 65, $exempt, $pc, 90, 11],
            ['Bakery', 'Festive Brown Bread 600g', 82, 95, $exempt, $pc, 40, 4],
            ['Beverages', 'Coca-Cola 500ml', 52, 70, $vat, $pc, 144, 8],
            ['Beverages', 'Dasani Water 1L', 40, 60, $vat, $pc, 96, 6],
            ['Beverages', 'Ketepa Pride Tea 100g', 88, 105, $vat, $pc, 50, 4],
            ['Beverages', 'Nescafe Classic 50g', 230, 275, $vat, $pc, 24, 2],
            ['Cooking', 'Fresh Fri Cooking Oil 1L', 290, 335, $vat, $pc, 60, 6],
            ['Cooking', 'Kimbo Cooking Fat 1kg', 310, 355, $vat, $pc, 30, 3],
            ['Cooking', 'Royco Mchuzi Mix 75g', 38, 50, $vat, $pc, 80, 5],
            ['Cooking', 'Kensalt 1kg', 30, 40, $vat, $pc, 60, 3],
            ['Household', 'Menengai Bar Soap 800g', 140, 165, $vat, $pc, 48, 4],
            ['Household', 'Omo Washing Powder 1kg', 245, 290, $vat, $pc, 30, 3],
            ['Household', 'Jik Bleach 750ml', 120, 145, $vat, $pc, 24, 2],
            ['Household', 'Rosy Tissue 10-pack', 380, 450, $vat, $pc, 20, 2],
            ['Personal Care', 'Colgate Toothpaste 140g', 160, 195, $vat, $pc, 30, 2],
            ['Personal Care', 'Geisha Soap 225g', 75, 95, $vat, $pc, 40, 3],
            ['Personal Care', 'Always Pads Regular', 95, 120, $vat, $pc, 36, 2],
            ['Snacks', 'Tropical Heat Crisps 50g', 38, 50, $vat, $pc, 60, 4],
            ['Snacks', 'Manji Digestive Biscuits', 58, 75, $vat, $pc, 50, 3],
            ['Snacks', 'Big G Bubble Gum (box)', 160, 200, $vat, $pc, 10, 1],
            ['Fresh', 'Eggs (tray of 30)', 420, 480, $exempt, $pc, 20, 4],
            ['Fresh', 'Tomatoes', 70, 100, $exempt, $kg, 30, 5],
            ['Fresh', 'Onions', 90, 120, $exempt, $kg, 25, 4],
        ];

        $products = [];

        foreach ($catalogue as $i => [$cat, $name, $cost, $price, $tax, $unit, $opening, $weight]) {
            $category = Category::firstOrCreate(['name' => $cat]);
            Carbon::setTestNow($today->copy()->subDays(31));

            $product = Product::create([
                'name' => $name,
                'sku' => 'SKU-' . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                'category_id' => $category->id,
                'unit_id' => $unit,
                'tax_rate_id' => $tax,
                'cost_price' => $cost,
                'selling_price' => $price,
                'reorder_level' => max(5, intdiv($opening, 6)),
                'is_weighed' => $unit === $kg,
                'track_stock' => true,
                'is_active' => true,
                'wholesale_price' => $price >= 150 ? round($price * 0.93) : null,
                'wholesale_min_qty' => $price >= 150 ? 6 : null,
            ]);
            $product->barcodes()->create(['barcode' => '616' . str_pad((string) (1000000 + $i), 10, '0', STR_PAD_LEFT), 'pack_quantity' => 1]);
            $stock->move($product, $branch->id, $opening, 'opening', $admin->id, $cost, reason: 'Opening stock');
            Carbon::setTestNow();

            $products[] = ['product' => $product, 'weight' => $weight, 'decimal' => $unit === $kg];
        }

        // Soda by the crate
        $soda = Product::where('name', 'Coca-Cola 500ml')->first();
        $soda->packagings()->create(['name' => 'Crate', 'quantity' => 24, 'selling_price' => 1560, 'is_active' => true, 'is_default_purchase' => true]);

        $customers = collect([
            ['Mama Njeri', '0712 345 678', 5000, 'retail'],
            ['Otieno Hardware & Shop', '0733 222 101', 20000, 'wholesale'],
            ['Wanjiru Kibandaski', '0701 998 877', 3000, 'retail'],
            ['Kamau Mutua', '0722 456 123', 2000, 'retail'],
            ['Achieng Groceries', '0745 111 222', 15000, 'wholesale'],
        ])->map(fn ($c) => Customer::create(['name' => $c[0], 'phone' => str_replace(' ', '', $c[1]), 'credit_limit' => $c[2], 'price_level' => $c[3], 'balance' => 0]));

        $pool = collect($products)->flatMap(fn ($p) => array_fill(0, $p['weight'], $p))->values();

        // 30 days of trading, busier on weekends and around month end
        for ($day = 29; $day >= 0; $day--) {
            $date = $today->copy()->subDays($day);
            $staff = $day % 3 === 0 ? $admin : $cashier;
            Carbon::setTestNow($date->copy()->setTime(7, 30));

            $shift = Shift::create([
                'branch_id' => $branch->id, 'till_id' => $till->id, 'user_id' => $staff->id,
                'status' => 'open', 'opened_at' => now(), 'opening_float' => 2000,
            ]);

            // Deliveries every 4 days top the shelves back up
            if ($day % 4 === 0) {
                Carbon::setTestNow($date->copy()->setTime(7, 0));
                foreach ($products as $p) {
                    $have = (float) $p['product']->stockLevels()->where('branch_id', $branch->id)->value('quantity');
                    $target = max(20, $p['weight'] * 12);
                    if ($have < $target) {
                        $stock->move($p['product'], $branch->id, $target - $have, 'purchase', $admin->id, (float) $p['product']->cost_price, reason: 'Weekly delivery');
                    }
                }
            }

            $count = 14 + mt_rand(0, 10) + ($date->isWeekend() ? 10 : 0) + ($date->day >= 28 || $date->day <= 3 ? 8 : 0);

            for ($n = 0; $n < $count; $n++) {
                $at = $date->copy()->setTime(7, 45)->addMinutes((int) (($n + mt_rand(0, 100) / 100) * (720 / $count)));
                if ($at->isFuture()) {
                    break; // today: only sales up to now
                }
                Carbon::setTestNow($at);
                $lines = [];

                foreach (range(1, mt_rand(1, 5)) as $_) {
                    $pick = $pool[mt_rand(0, $pool->count() - 1)];
                    $id = $pick['product']->id;
                    $qty = $pick['decimal'] ? mt_rand(1, 6) / 2 : mt_rand(1, 3);
                    $lines[$id] = ['product_id' => $id, 'quantity' => ($lines[$id]['quantity'] ?? 0) + $qty, 'product_unit_id' => null];
                }

                $total = collect($lines)->sum(fn ($l) => round(Product::find($l['product_id'])->priceFor($l['quantity']) * $l['quantity'], 2));
                $roll = mt_rand(1, 100);
                $customer = $roll > 92 ? $customers->random() : null;

                $payments = match (true) {
                    $customer !== null => [['method' => 'credit', 'amount' => $total]],
                    $roll > 45 => [['method' => 'mpesa', 'amount' => $total, 'reference' => strtoupper(Str::random(10))]],
                    default => [['method' => 'cash', 'amount' => ceil($total / 50) * 50]],
                };

                try {
                    $sales->complete(array_values($lines), $payments, $staff, $shift, $customer?->id);
                } catch (\Throwable $e) {
                    // out of stock etc.: skip this basket
                }
            }

            if ($day % 4 === 1) {
                Carbon::setTestNow($date->copy()->setTime(13, 0));
                Expense::create([
                    'branch_id' => $branch->id, 'shift_id' => $shift->id, 'user_id' => $staff->id,
                    'category' => collect(['transport', 'meals', 'supplies', 'airtime'])->random(),
                    'description' => collect(['Boda boda to Gikomba', 'Staff lunch', 'Carrier bags', 'Safaricom data'])->random(),
                    'amount' => [150, 300, 450, 500][mt_rand(0, 3)], 'method' => 'cash', 'spent_at' => now(),
                ]);
            }

            // Close every day except today
            if ($day > 0) {
                Carbon::setTestNow($date->copy()->setTime(20, 30));
                $summary = $shift->summary();
                $short = mt_rand(1, 10) === 1 ? -mt_rand(1, 4) * 50 : 0;
                $shift->update([
                    'status' => 'closed', 'closed_at' => now(), 'closed_by' => $staff->id,
                    'expected_cash' => $summary['expected_cash'], 'counted_cash' => $summary['expected_cash'] + $short,
                    'variance' => $short, 'notes' => $short ? 'Short at cash-up' : null,
                ]);
            }
        }

        Carbon::setTestNow($today->copy()->startOfMonth()->setTime(9, 0));
        foreach ([['rent', 'Shop rent', 25000, 'bank'], ['electricity', 'KPLC tokens', 3500, 'mpesa'], ['wages', 'Casual wages', 6000, 'mpesa']] as [$cat, $desc, $amt, $method]) {
            Expense::create([
                'branch_id' => $branch->id, 'user_id' => $admin->id, 'category' => $cat, 'description' => $desc,
                'amount' => $amt, 'method' => $method, 'reference' => strtoupper(Str::random(10)), 'spent_at' => now(),
            ]);
        }

        Carbon::setTestNow();
    }
}
