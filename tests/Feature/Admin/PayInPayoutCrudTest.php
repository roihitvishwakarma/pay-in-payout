<?php

namespace Tests\Feature\Admin;

use App\Enums\PaymentStatus;
use App\Models\Merchant;
use App\Models\PayIn;
use App\Models\Payout;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the pay-in/payout admin CRUD lists and their filters.
 */
class PayInPayoutCrudTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::forceCreate([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('secret'),
        ]);
    }

    private function merchant(string $suffix = 'a'): Merchant
    {
        return Merchant::create([
            'name' => "Merchant {$suffix}",
            'email' => "merchant.{$suffix}@example.com",
            'api_key' => "key-{$suffix}-" . uniqid(),
        ]);
    }

    public function test_unauthenticated_access_is_redirected(): void
    {
        $this->get('admin/pay-in')->assertRedirect();
        $this->get('admin/payout')->assertRedirect();
    }

    public function test_authenticated_admin_can_view_lists(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'backpack')->get('admin/pay-in')->assertOk();
        $this->actingAs($admin, 'backpack')->get('admin/payout')->assertOk();
    }

    public function test_status_filter_returns_only_matching_records(): void
    {
        $admin = $this->admin();
        $merchant = $this->merchant();

        PayIn::create([
            'merchant_id' => $merchant->id,
            'transaction_id' => 'TXN-PI-1',
            'amount' => '10.00',
            'status' => PaymentStatus::Success,
        ]);
        PayIn::create([
            'merchant_id' => $merchant->id,
            'transaction_id' => 'TXN-PI-2',
            'amount' => '20.00',
            'status' => PaymentStatus::Failed,
        ]);

        // Backpack lists load rows via the AJAX search endpoint, so assert against that.
        $response = $this->actingAs($admin, 'backpack')->post('admin/pay-in/search?status=success');
        $response->assertOk();
        $response->assertSee('TXN-PI-1');
        $response->assertDontSee('TXN-PI-2');
    }

    public function test_merchant_filter_returns_only_matching_records(): void
    {
        $admin = $this->admin();
        $m1 = $this->merchant('one');
        $m2 = $this->merchant('two');

        Payout::create([
            'merchant_id' => $m1->id,
            'transaction_id' => 'TXN-PO-M1',
            'amount' => '15.00',
            'status' => PaymentStatus::Pending,
        ]);
        Payout::create([
            'merchant_id' => $m2->id,
            'transaction_id' => 'TXN-PO-M2',
            'amount' => '25.00',
            'status' => PaymentStatus::Pending,
        ]);

        $response = $this->actingAs($admin, 'backpack')->post('admin/payout/search?merchant_id=' . $m1->id);
        $response->assertOk();
        $response->assertSee('TXN-PO-M1');
        $response->assertDontSee('TXN-PO-M2');
    }

    public function test_date_range_filter_is_inclusive_of_boundaries(): void
    {
        $admin = $this->admin();
        $merchant = $this->merchant();

        $onBoundary = PayIn::create([
            'merchant_id' => $merchant->id,
            'transaction_id' => 'TXN-PI-BOUNDARY',
            'amount' => '10.00',
            'status' => PaymentStatus::Pending,
        ]);
        // Place the record exactly at the end boundary date, late in the day.
        $onBoundary->forceFill(['created_at' => '2024-06-15 23:30:00'])->save(); // end of the boundary day

        $outside = PayIn::create([
            'merchant_id' => $merchant->id,
            'transaction_id' => 'TXN-PI-OUTSIDE',
            'amount' => '10.00',
            'status' => PaymentStatus::Pending,
        ]);
        $outside->forceFill(['created_at' => '2024-06-16 00:05:00'])->save();

        $response = $this->actingAs($admin, 'backpack')
            ->post('admin/pay-in/search?date_from=2024-06-15&date_to=2024-06-15');
        $response->assertOk();
        $response->assertSee('TXN-PI-BOUNDARY');
        $response->assertDontSee('TXN-PI-OUTSIDE');
    }

    public function test_empty_filter_result_renders_without_error(): void
    {
        $admin = $this->admin();
        $merchant = $this->merchant();

        PayIn::create([
            'merchant_id' => $merchant->id,
            'transaction_id' => 'TXN-PI-ONLY',
            'amount' => '10.00',
            'status' => PaymentStatus::Success,
        ]);

        // Nothing is "failed", but the page should still render.
        $response = $this->actingAs($admin, 'backpack')->get('admin/pay-in?status=failed');
        $response->assertOk();
        $response->assertDontSee('TXN-PI-ONLY');
    }
}
