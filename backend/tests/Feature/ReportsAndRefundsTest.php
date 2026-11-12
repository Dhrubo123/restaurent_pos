<?php

namespace Tests\Feature;

use App\Models\DiningTable;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Permission;
use App\Models\Restaurant;
use App\Models\Role;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsAndRefundsTest extends TestCase
{
    use RefreshDatabase;

    public function test_partial_and_full_refunds_enforce_balance_and_reconcile_shift_and_reports(): void
    {
        [$waiter, $cashier, $manager, $table, $steak] = $this->setupFixtures();

        $this->actingAs($cashier)->postJson('/api/v1/shifts/open', [
            'opening_float' => 1000,
        ])->assertCreated();

        $order = $this->actingAs($waiter)
            ->postJson("/api/v1/tables/{$table->id}/orders", ['guest_count' => 2])
            ->assertCreated()
            ->json('data');

        $order = $this->actingAs($waiter)->postJson("/api/v1/orders/{$order['id']}/items", [
            'version' => $order['version'],
            'menu_item_id' => $steak->id,
            'quantity' => 1,
        ])->assertCreated()->json('data');

        $checkout = $this->actingAs($cashier)
            ->withHeader('Idempotency-Key', 'chk-ref-1')
            ->postJson("/api/v1/orders/{$order['id']}/checkout", [
                'version' => $order['version'],
                'method' => 'CASH',
                'amount' => 1000,
            ])->assertCreated()->json();

        $invoice = $checkout['invoice'];
        $this->assertSame('ISSUED', $invoice['status']);
        $this->assertSame('1000.00', $invoice['total']);

        // Cashier without refund-payment permission cannot refund or void
        $this->actingAs($cashier)
            ->withoutHeader('Idempotency-Key')
            ->postJson("/api/v1/invoices/{$invoice['id']}/refunds", [
                'amount' => 300,
                'method' => 'CASH',
                'reason' => 'Unauthorized cashier refund attempt',
            ])->assertForbidden();

        // Manager issues partial refund of 300 BDT
        $partialRefund = $this->actingAs($manager)
            ->withHeader('Idempotency-Key', 'ref-1')
            ->postJson("/api/v1/invoices/{$invoice['id']}/refunds", [
                'amount' => 300,
                'method' => 'CASH',
                'reason' => 'Side dish cold — partial courtesy refund',
            ])->assertCreated()->json();

        $this->assertSame('REF-0001', $partialRefund['refund']['refund_number']);
        $this->assertSame('PARTIALLY_REFUNDED', $partialRefund['data']['status']);
        $this->assertSame('300.00', $partialRefund['data']['refunded_amount']);

        // Cannot void an invoice that already has a partial refund
        $this->actingAs($manager)
            ->withoutHeader('Idempotency-Key')
            ->postJson("/api/v1/invoices/{$invoice['id']}/void", [
                'reason' => 'Attempt void after refund',
            ])->assertConflict();

        // Refunding more than remaining 700 BDT is rejected
        $this->actingAs($manager)
            ->withoutHeader('Idempotency-Key')
            ->postJson("/api/v1/invoices/{$invoice['id']}/refunds", [
                'amount' => 750,
                'method' => 'CASH',
                'reason' => 'Exceeds remaining refundable balance',
            ])->assertConflict();

        // Expected cash in open shift is now 1000 float + 1000 sale - 300 refund = 1700.00
        $shiftCurrent = $this->actingAs($cashier)
            ->getJson('/api/v1/shifts/current')
            ->assertOk()
            ->json('data.current_shift');

        $this->assertSame('1700.00', $shiftCurrent['expected_cash']);
        $this->assertSame('700.00', $shiftCurrent['totals_by_method']['CASH']);

        // Reports summary reconciles net revenue = 1000 - 300 = 700.00
        $summary = $this->actingAs($manager)
            ->getJson('/api/v1/reports/summary')
            ->assertOk()
            ->json('data');

        $this->assertSame(1, $summary['kpis']['invoices_count']);
        $this->assertSame('1000.00', $summary['kpis']['billed_total']);
        $this->assertSame('300.00', $summary['kpis']['refunded_total']);
        $this->assertSame('700.00', $summary['kpis']['net_revenue']);
        $this->assertSame('700.00', $summary['payments_by_method']['CASH']['net']);

        // CSV Export works
        $csvResponse = $this->actingAs($manager)->get('/api/v1/reports/export');
        $csvResponse->assertOk();
        $this->assertStringContainsString('INV-0001', $csvResponse->streamedContent());
    }

    public function test_manager_can_void_invoice_preserving_sequence_and_excluding_from_net_sales(): void
    {
        [$waiter, $cashier, $manager, $table, $steak] = $this->setupFixtures();

        $this->actingAs($cashier)->postJson('/api/v1/shifts/open', [
            'opening_float' => 500,
        ])->assertCreated();

        $order = $this->actingAs($waiter)
            ->postJson("/api/v1/tables/{$table->id}/orders", ['guest_count' => 2])
            ->assertCreated()
            ->json('data');

        $order = $this->actingAs($waiter)->postJson("/api/v1/orders/{$order['id']}/items", [
            'version' => $order['version'],
            'menu_item_id' => $steak->id,
            'quantity' => 1,
        ])->assertCreated()->json('data');

        $invoice = $this->actingAs($cashier)
            ->postJson("/api/v1/orders/{$order['id']}/checkout", [
                'version' => $order['version'],
                'method' => 'CASH',
                'amount' => 1000,
            ])->assertCreated()->json('invoice');

        // Cashier cannot void
        $this->actingAs($cashier)->postJson("/api/v1/invoices/{$invoice['id']}/void", [
            'reason' => 'Cashier tries to void',
        ])->assertForbidden();

        // Manager voids invoice
        $voided = $this->actingAs($manager)->postJson("/api/v1/invoices/{$invoice['id']}/void", [
            'reason' => 'Entered on wrong table and settled by mistake',
        ])->assertOk()->json('data');

        $this->assertSame('VOIDED', $voided['status']);
        $this->assertSame('INV-0001', $voided['invoice_number']);

        // Next invoice gets INV-0002 (never reuses INV-0001)
        $order2 = $this->actingAs($waiter)
            ->postJson("/api/v1/tables/{$table->id}/orders", ['guest_count' => 1])
            ->assertCreated()
            ->json('data');

        $order2 = $this->actingAs($waiter)->postJson("/api/v1/orders/{$order2['id']}/items", [
            'version' => $order2['version'],
            'menu_item_id' => $steak->id,
            'quantity' => 1,
        ])->assertCreated()->json('data');

        $invoice2 = $this->actingAs($cashier)
            ->postJson("/api/v1/orders/{$order2['id']}/checkout", [
                'version' => $order2['version'],
                'method' => 'CASH',
                'amount' => 1000,
            ])->assertCreated()->json('invoice');

        $this->assertSame('INV-0002', $invoice2['invoice_number']);

        // Reports summary shows 1 active invoice, 1 voided invoice, and 1000.00 net revenue
        $summary = $this->actingAs($manager)
            ->getJson('/api/v1/reports/summary')
            ->assertOk()
            ->json('data');

        $this->assertSame(1, $summary['kpis']['invoices_count']);
        $this->assertSame(1, $summary['kpis']['voided_invoices_count']);
        $this->assertSame('1000.00', $summary['kpis']['net_revenue']);
        $this->assertSame('1000.00', $summary['kpis']['voided_total']);

        // Audit trail logs invoice.voided with reason
        $audit = $this->actingAs($manager)
            ->getJson('/api/v1/audit-events?action=invoice.voided')
            ->assertOk()
            ->json('data');

        $this->assertNotEmpty($audit);
        $this->assertSame('invoice.voided', $audit[0]['action']);
        $this->assertSame('Entered on wrong table and settled by mistake', $audit[0]['reason']);
    }

    /**
     * @return array{0: User, 1: User, 2: User, 3: DiningTable, 4: MenuItem}
     */
    private function setupFixtures(): array
    {
        $restaurant = Restaurant::query()->create([
            'name' => 'Test Bistro',
            'timezone' => 'Asia/Dhaka',
            'currency_code' => 'BDT',
            'active' => true,
        ]);

        $perms = collect([
            'view-floor',
            'manage-floor',
            'manage-menu',
            'manage-orders',
            'take-payment',
            'refund-payment',
            'view-reports',
        ])->mapWithKeys(fn ($k) => [
            $k => Permission::query()->firstOrCreate(['key' => $k], ['name' => $k]),
        ]);

        $waiterRole = Role::query()->create(['key' => 'waiter', 'name' => 'Waiter']);
        $waiterRole->permissions()->sync([$perms['view-floor']->id, $perms['manage-orders']->id]);

        $cashierRole = Role::query()->create(['key' => 'cashier', 'name' => 'Cashier']);
        $cashierRole->permissions()->sync([
            $perms['view-floor']->id,
            $perms['manage-orders']->id,
            $perms['take-payment']->id,
        ]);

        $managerRole = Role::query()->create(['key' => 'manager', 'name' => 'Manager']);
        $managerRole->permissions()->sync($perms->pluck('id'));

        $waiter = User::factory()->create(['restaurant_id' => $restaurant->id, 'active' => true]);
        $waiter->roles()->sync([$waiterRole->id]);

        $cashier = User::factory()->create(['restaurant_id' => $restaurant->id, 'active' => true]);
        $cashier->roles()->sync([$cashierRole->id]);

        $manager = User::factory()->create(['restaurant_id' => $restaurant->id, 'active' => true]);
        $manager->roles()->sync([$managerRole->id]);

        $zone = Zone::query()->create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Main Hall',
            'sort_order' => 1,
            'active' => true,
        ]);

        $table = DiningTable::query()->create([
            'restaurant_id' => $restaurant->id,
            'zone_id' => $zone->id,
            'code' => 'T1',
            'display_name' => 'Table 1',
            'capacity' => 4,
            'sort_order' => 1,
            'active' => true,
        ]);

        $category = MenuCategory::query()->create([
            'restaurant_id' => $restaurant->id,
            'name' => 'Steaks',
            'default_station' => 'KITCHEN',
            'sort_order' => 1,
            'active' => true,
        ]);

        $steak = MenuItem::query()->create([
            'restaurant_id' => $restaurant->id,
            'menu_category_id' => $category->id,
            'sku' => 'STK-1000',
            'name' => 'Ribeye Steak',
            'price' => 1000,
            'available' => true,
            'active' => true,
        ]);

        return [$waiter, $cashier, $manager, $table, $steak];
    }
}
