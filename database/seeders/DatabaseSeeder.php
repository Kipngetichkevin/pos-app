<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Category;
use App\Models\TaxRate;
use App\Models\Till;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Branch
        $branch = Branch::firstOrCreate(
            ['code' => 'MAIN'],
            ['name' => 'Main Branch']
        );

        // Till
        Till::firstOrCreate(
            ['branch_id' => $branch->id, 'code' => 'T01'],
            ['name' => 'Till 1']
        );

        // Admin user (password and PIN are hashed automatically)
        User::firstOrCreate(
            ['username' => 'admin'],
            [
                'branch_id' => $branch->id,
                'name' => 'Administrator',
                'email' => 'admin@pos.local',
                'password' => 'admin123',
                'pin' => '1234',
                'role' => 'admin',
            ]
        );

        // Units of measure
        $units = [
            ['name' => 'Piece', 'short_name' => 'pc', 'allows_decimal' => false],
            ['name' => 'Kilogram', 'short_name' => 'kg', 'allows_decimal' => true],
            ['name' => 'Gram', 'short_name' => 'g', 'allows_decimal' => true],
            ['name' => 'Litre', 'short_name' => 'ltr', 'allows_decimal' => true],
            ['name' => 'Packet', 'short_name' => 'pkt', 'allows_decimal' => false],
            ['name' => 'Crate', 'short_name' => 'crt', 'allows_decimal' => false],
            ['name' => 'Carton', 'short_name' => 'ctn', 'allows_decimal' => false],
            ['name' => 'Dozen', 'short_name' => 'dz', 'allows_decimal' => false],
        ];

        foreach ($units as $unit) {
            Unit::firstOrCreate(['short_name' => $unit['short_name']], $unit);
        }

        // Tax rates (Kenya). Codes follow the KRA eTIMS tax categories.
        $taxRates = [
            ['code' => 'A', 'name' => 'Exempt', 'rate' => 0],
            ['code' => 'B', 'name' => 'VAT 16%', 'rate' => 16],
            ['code' => 'C', 'name' => 'Zero-rated', 'rate' => 0],
            ['code' => 'D', 'name' => 'Non-VAT', 'rate' => 0],
            ['code' => 'E', 'name' => 'VAT 8%', 'rate' => 8],
        ];

        foreach ($taxRates as $tax) {
            TaxRate::firstOrCreate(['code' => $tax['code']], $tax);
        }

        // Default category
        Category::firstOrCreate(['name' => 'General']);
    }
}