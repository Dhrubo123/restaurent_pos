<?php

namespace Tests;

use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function createLegacyOwnerInvoice(int $orderId): Invoice
    {
        $order = Order::query()->with('table')->findOrFail($orderId);
        $order->update(['status' => 'OWNER_DUE']);

        return $order->invoice()->create([
            'restaurant_id' => $order->restaurant_id,
            'invoice_number' => 'INV-0001',
            'status' => 'OWNER_DUE',
            'owner_account' => true,
            'table_code' => $order->table->code,
            'table_name' => $order->table->display_name,
            'subtotal' => $order->subtotal,
            'total' => $order->total,
            'paid_amount' => 0,
            'issued_at' => now(),
        ]);
    }
}
