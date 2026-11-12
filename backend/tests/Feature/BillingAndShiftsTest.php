<?php

namespace Tests\Feature;

use App\Models\DiningTable;
use App\Models\Invoice;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Permission;
use App\Models\Restaurant;
use App\Models\Role;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingAndShiftsTest extends TestCase
{
    use RefreshDatabase;

    public function test_discount_limit_enforces_5_percent_cap_for_waiter_and_allows_manager_override(): void
    {
        [$waiter, , $manager, , $table, $steak] = $this->billingSetup();

        $order = $this->actingAs($waiter)
            ->postJson("/api/v1/tables/{$table->id}/orders", ['guest_count' => 2])
            ->assertCreated()
            ->json('data');

        $order = $this->actingAs($waiter)->postJson("/api/v1/orders/{$order['id']}/items", [
            'version' => $order['version'],
            'menu_item_id' => $steak->id,
            'quantity' => 1,
        ])->assertCreated()->json('data');

        $afterWaiterDiscount = $this->actingAs($waiter)->postJson("/api/v1/orders/{$order['id']}/discounts", [
            'version' => $order['version'],
            'type' => 'PERCENTAGE',
            'value' => 5,
            'reason' => 'Regular guest courtesy',
        ])->assertOk()->json('data');

        $this->assertSame('50.00', $afterWaiterDiscount['discount_amount']);
        $this->assertSame('950.00', $afterWaiterDiscount['total']);

        $this->actingAs($waiter)->postJson("/api/v1/orders/{$order['id']}/discounts", [
            'version' => $afterWaiterDiscount['version'],
            'type' => 'PERCENTAGE',
            'value' => 10,
            'reason' => 'Try 10 percent as waiter',
            'approved_by' => $manager->id,
        ])->assertForbidden();

        $afterManagerDiscount = $this->actingAs($manager)->postJson("/api/v1/orders/{$order['id']}/discounts", [
            'version' => $afterWaiterDiscount['version'],
            'type' => 'PERCENTAGE',
            'value' => 10,
            'reason' => 'Manager approved promotion',
        ])->assertOk()->json('data');

        $this->assertSame('100.00', $afterManagerDiscount['discount_amount']);
        $this->assertSame('900.00', $afterManagerDiscount['total']);
    }

    public function test_split_payment_partial_status_and_atomic_checkout_releases_table(): void
    {
        [$waiter, $cashier, , , $table, $steak] = $this->billingSetup();

        $order = $this->actingAs($waiter)
            ->postJson("/api/v1/tables/{$table->id}/orders", ['guest_count' => 2])
            ->assertCreated()
            ->json('data');

        $order = $this->actingAs($waiter)->postJson("/api/v1/orders/{$order['id']}/items", [
            'version' => $order['version'],
            'menu_item_id' => $steak->id,
            'quantity' => 1,
        ])->assertCreated()->json('data');

        $this->actingAs($cashier)->postJson("/api/v1/orders/{$order['id']}/payments", [
            'version' => $order['version'],
            'method' => 'CASH',
            'amount' => 400,
        ])->assertConflict();

        $this->actingAs($cashier)->postJson('/api/v1/shifts/open', [
            'opening_float' => 1000,
        ])->assertCreated();

        $afterFirstPayment = $this->actingAs($cashier)
            ->withHeader('Idempotency-Key', 'pay-part-1')
            ->postJson("/api/v1/orders/{$order['id']}/payments", [
                'version' => $order['version'],
                'method' => 'CASH',
                'amount' => 400,
            ])->assertCreated()->json('data');

        $this->assertSame('PARTIALLY_PAID', $afterFirstPayment['status']);
        $this->assertSame('400.00', $afterFirstPayment['paid_amount']);
        $this->assertSame('600.00', $afterFirstPayment['balance_due']);
        $this->assertNotNull($table->fresh()->openOrder());

        $this->actingAs($cashier)
            ->withoutHeader('Idempotency-Key')
            ->postJson("/api/v1/orders/{$order['id']}/checkout", [
                'version' => $afterFirstPayment['version'],
            ])->assertConflict();

        $checkout = $this->actingAs($cashier)
            ->withHeader('Idempotency-Key', 'checkout-order-1')
            ->postJson("/api/v1/orders/{$order['id']}/checkout", [
                'version' => $afterFirstPayment['version'],
                'method' => 'BKASH',
                'amount' => 600,
                'provider_reference' => 'BKASH-TRX-9988',
            ])->assertCreated();

        $this->assertSame('PAID', $checkout->json('data.status'));
        $this->assertSame('INV-0001', $checkout->json('invoice.invoice_number'));
        $this->assertSame('1000.00', $checkout->json('invoice.total'));
        $this->assertNull($table->fresh()->openOrder());
        $this->assertSame(1, Invoice::query()->count());
    }

    public function test_cash_change_calculation_non_cash_overpayment_guard_and_shift_variance_approval(): void
    {
        [, $cashier, $manager, , $table, $steak] = $this->billingSetup();

        $shift = $this->actingAs($cashier)->postJson('/api/v1/shifts/open', [
            'opening_float' => 500,
        ])->assertCreated()->json('data');

        $order = $this->actingAs($cashier)
            ->postJson("/api/v1/tables/{$table->id}/orders", ['guest_count' => 1])
            ->assertCreated()
            ->json('data');

        $order = $this->actingAs($cashier)->postJson("/api/v1/orders/{$order['id']}/items", [
            'version' => $order['version'],
            'menu_item_id' => $steak->id,
            'quantity' => 1,
        ])->assertCreated()->json('data');

        $this->actingAs($cashier)->postJson("/api/v1/orders/{$order['id']}/payments", [
            'version' => $order['version'],
            'method' => 'CARD',
            'amount' => 1200,
            'provider_reference' => 'CARD-1234',
        ])->assertConflict();

        $checkout = $this->actingAs($cashier)->postJson("/api/v1/orders/{$order['id']}/checkout", [
            'version' => $order['version'],
            'method' => 'CASH',
            'amount' => 1000,
            'tendered_amount' => 1200,
        ])->assertCreated();

        $this->assertSame('1000.00', $checkout->json('invoice.paid_amount'));
        $this->assertSame('1200.00', $checkout->json('invoice.tendered_cash'));
        $this->assertSame('200.00', $checkout->json('invoice.change_given'));

        $closedShift = $this->actingAs($cashier)->postJson("/api/v1/shifts/{$shift['id']}/close", [
            'counted_cash' => 1480,
            'notes' => 'Short by 20 BDT',
        ])->assertOk()->json('data');

        $this->assertSame('1500.00', $closedShift['expected_cash']);
        $this->assertSame('-20.00', $closedShift['variance']);

        $this->actingAs($cashier)->postJson("/api/v1/shifts/{$shift['id']}/approve")
            ->assertForbidden();

        $this->actingAs($manager)->postJson("/api/v1/shifts/{$shift['id']}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'APPROVED');
    }

    private function billingSetup(): array
    {
        $restaurant = Restaurant::query()->create([
            'name' => 'Test Restaurant',
            'timezone' => 'Asia/Dhaka',
            'currency_code' => 'BDT',
        ]);
        $zone = Zone::query()->create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Indoor',
            'active' => true,
        ]);
        $table = DiningTable::query()->create([
            'restaurant_id' => $restaurant->id,
            'zone_id' => $zone->id,
            'code' => 'T1',
            'display_name' => 'Table 1',
            'capacity' => 4,
            'active' => true,
        ]);

        $category = MenuCategory::query()->create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Mains',
            'default_station' => 'KITCHEN',
            'active' => true,
        ]);

        $steak = MenuItem::query()->create([
            'restaurant_id' => $restaurant->id,
            'menu_category_id' => $category->id,
            'sku' => 'STK-01',
            'name' => 'Ribeye Steak',
            'station' => 'KITCHEN',
            'price' => 1000,
            'active' => true,
            'available' => true,
        ]);

        $waiter = $this->userWithPermissions($restaurant, ['view-floor', 'manage-orders']);
        $cashier = $this->userWithPermissions($restaurant, ['view-floor', 'manage-orders', 'take-payment']);
        $manager = $this->userWithPermissions($restaurant, ['view-floor', 'manage-floor', 'manage-menu', 'manage-orders', 'take-payment']);

        return [$waiter, $cashier, $manager, $restaurant, $table, $steak];
    }

    /**
     * @param  list<string>  $permissions
     */
    private function userWithPermissions(Restaurant $restaurant, array $permissions): User
    {
        $user = User::factory()->create(['restaurant_id' => $restaurant->id, 'active' => true]);
        $role = Role::query()->create(['key' => 'role-'.uniqid(), 'name' => 'Role']);

        foreach ($permissions as $permKey) {
            $perm = Permission::query()->firstOrCreate(['key' => $permKey], ['name' => $permKey]);
            $role->permissions()->attach($perm);
        }

        $user->roles()->attach($role);

        return $user;
    }
}
