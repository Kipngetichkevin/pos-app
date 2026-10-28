<?php

namespace Tests\Feature;

use App\Models\Expense;
use Tests\PosTestCase;

class ExpenseTest extends PosTestCase
{
    public function test_cash_expense_reduces_expected_cash_in_the_drawer(): void
    {
        $shift = $this->openShift($this->admin, 1000);

        $this->actingAs($this->admin)->post('/expenses', [
            'category' => 'electricity',
            'description' => 'KPLC tokens',
            'amount' => 250,
            'method' => 'cash',
        ])->assertRedirect('/expenses');

        $this->assertSame($shift->id, Expense::first()->shift_id);
        $this->assertSame(750.0, $shift->summary()['expected_cash']);

        $this->actingAs($this->admin)->get('/expenses')->assertOk()->assertSee('KPLC tokens');
        $this->actingAs($this->admin)->get("/shifts/{$shift->id}/close")->assertOk()->assertSee('Cash expenses');
    }

    public function test_cash_needs_an_open_shift_but_mpesa_does_not(): void
    {
        $this->actingAs($this->admin)->post('/expenses', [
            'category' => 'rent', 'description' => 'October rent', 'amount' => 15000, 'method' => 'cash',
        ])->assertSessionHasErrors('method');

        $this->actingAs($this->admin)->post('/expenses', [
            'category' => 'rent', 'description' => 'October rent', 'amount' => 15000, 'method' => 'mpesa',
        ])->assertSessionHasErrors('reference');

        $this->actingAs($this->admin)->post('/expenses', [
            'category' => 'rent', 'description' => 'October rent', 'amount' => 15000, 'method' => 'mpesa', 'reference' => 'tjk4abc123',
        ])->assertSessionHasNoErrors();

        $this->assertSame('TJK4ABC123', Expense::first()->reference);
        $this->assertNull(Expense::first()->shift_id);
    }

    public function test_only_managers_remove_and_removal_is_soft(): void
    {
        $cashier = $this->cashier();
        $this->openShift($cashier);

        $this->actingAs($cashier)->post('/expenses', [
            'category' => 'meals', 'description' => 'Tea', 'amount' => 60, 'method' => 'cash',
        ])->assertSessionHasNoErrors();

        $expense = Expense::firstOrFail();

        $this->actingAs($cashier)->delete("/expenses/{$expense->id}")->assertForbidden();
        $this->actingAs($this->admin)->delete("/expenses/{$expense->id}")->assertSessionHasNoErrors();

        $this->assertSame(0, Expense::count());
        $this->assertSame(1, Expense::withTrashed()->count()); // still there to sync the removal
    }
}
