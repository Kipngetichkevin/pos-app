<?php

namespace Tests\Feature;

use App\Models\Customer;
use Tests\PosTestCase;

class PagesTest extends PosTestCase
{
    public function test_login_and_logout(): void
    {
        $this->post('/login', ['username' => 'admin', 'password' => 'wrong'])->assertSessionHasErrors('username');
        $this->post('/login', ['username' => 'admin', 'password' => 'admin123'])->assertRedirect('/');
        $this->assertAuthenticatedAs($this->admin);

        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_every_back_office_page_renders_for_a_manager(): void
    {
        $product = $this->product();
        $customer = $this->customer();
        $shift = $this->openShift($this->admin);

        $pages = [
            '/', '/pos', '/returns', '/customers', '/customers?filter=all',
            "/customers?edit={$customer->id}", "/customers?pay={$customer->id}", "/customers?statement={$customer->id}", '/customers?create=1',
            '/products', '/products?create=1', "/products?edit={$product->id}", "/products/{$product->id}/packaging",
            '/stock', '/stock?filter=low', "/stock?adjust={$product->id}", "/stock?history={$product->id}",
            '/stock/receive', "/stock/packs/{$product->id}",
            '/sales', '/sales?tab=mpesa', '/shifts', "/shifts/{$shift->id}/close",
            '/users', '/users?create=1', "/users?edit={$this->admin->id}",
            '/expenses', '/transfers', '/transfers/create', '/stock-takes', '/audit', '/etims',
            '/reports', '/reports?tab=products', '/reports?tab=products&by=qty', '/reports?tab=profit', '/reports?tab=vat', '/reports?tab=staff',
            '/reports?range=last_month', '/reports?from=2026-01-01&to=2026-01-31',
        ];

        foreach ($pages as $page) {
            $this->actingAs($this->admin)->get($page)->assertOk();
        }
    }

    public function test_cashiers_cannot_open_the_back_office(): void
    {
        $cashier = $this->cashier();

        $this->actingAs($cashier)->get('/pos')->assertOk()->assertSee('Open a shift');
        $this->actingAs($cashier)->get('/')->assertOk()->assertSee('Open shift &amp; start selling', false);
        $this->actingAs($cashier)->get('/customers')->assertOk();

        foreach (['/products', '/stock', '/stock/receive', '/sales', '/shifts', '/users', '/reports', '/reports/export'] as $page) {
            $this->actingAs($cashier)->get($page)->assertForbidden();
        }
    }

    public function test_customer_debt_payment(): void
    {
        $customer = $this->customer();
        $customer->update(['balance' => 500]);
        $this->openShift($this->admin);

        $this->actingAs($this->admin)
            ->post("/customers/{$customer->id}/payments", ['payAmount' => 200, 'payMethod' => 'cash'])
            ->assertSessionHasNoErrors();

        $this->assertSame(300.0, (float) Customer::find($customer->id)->balance);
    }
}
