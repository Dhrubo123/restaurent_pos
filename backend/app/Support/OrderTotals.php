<?php

namespace App\Support;

use App\Models\Discount;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\Schema;

final class OrderTotals
{
    public static function calculateLine(
        float $unitPrice,
        float $modifierUnitTotal,
        float $quantity,
        float $cancelledQuantity,
        float $taxRate,
        bool $taxInclusive,
    ): array {
        $effectiveQty = max(0.0, $quantity - $cancelledQuantity);
        $combinedUnit = round($unitPrice + $modifierUnitTotal, 2);
        $lineSubtotal = round($combinedUnit * $effectiveQty, 2);

        if ($taxRate <= 0) {
            $lineTax = 0.0;
            $lineTotal = $lineSubtotal;
        } elseif ($taxInclusive) {
            $lineTax = round($lineSubtotal - ($lineSubtotal / (1 + ($taxRate / 100))), 2);
            $lineTotal = $lineSubtotal;
        } else {
            $lineTax = round($lineSubtotal * ($taxRate / 100), 2);
            $lineTotal = round($lineSubtotal + $lineTax, 2);
        }

        return [
            'line_subtotal' => number_format($lineSubtotal, 2, '.', ''),
            'line_tax' => number_format($lineTax, 2, '.', ''),
            'line_total' => number_format($lineTotal, 2, '.', ''),
        ];
    }

    public static function recalculateOrder(Order $order): Order
    {
        $items = $order->items()->where('status', '!=', 'CANCELLED')->get();

        $subtotal = 0.0;
        $rawExclusiveTax = 0.0;

        /** @var OrderItem $item */
        foreach ($items as $item) {
            $line = self::calculateLine(
                (float) $item->unit_price,
                (float) $item->modifier_unit_total,
                (float) $item->quantity,
                (float) $item->cancelled_quantity,
                (float) $item->tax_rate,
                (bool) $item->tax_inclusive,
            );

            if (
                $item->line_subtotal !== $line['line_subtotal']
                || $item->line_tax !== $line['line_tax']
                || $item->line_total !== $line['line_total']
            ) {
                $item->update($line);
            }

            $subtotal += (float) $line['line_subtotal'];
            if (! $item->tax_inclusive) {
                $rawExclusiveTax += (float) $line['line_tax'];
            }
        }

        $discountAmount = (float) $order->discount_amount;
        if (Schema::hasTable('discounts')) {
            /** @var Discount|null $activeDiscount */
            $activeDiscount = $order->discounts()->latest('id')->first();
            if ($activeDiscount) {
                if ($activeDiscount->type === 'PERCENTAGE') {
                    $discountAmount = round($subtotal * ((float) $activeDiscount->value / 100), 2);
                } else {
                    $discountAmount = min($subtotal, round((float) $activeDiscount->value, 2));
                }
                if ((float) $activeDiscount->computed_amount !== $discountAmount) {
                    $activeDiscount->update(['computed_amount' => number_format($discountAmount, 2, '.', '')]);
                }
            } else {
                $discountAmount = 0.0;
            }
        }

        $discountRatio = $subtotal > 0 ? max(0.0, ($subtotal - $discountAmount) / $subtotal) : 1.0;
        $taxAmount = round($rawExclusiveTax * $discountRatio, 2);

        $serviceCharge = (float) $order->service_charge;
        $total = max(0.0, round($subtotal - $discountAmount + $serviceCharge + $taxAmount, 2));

        $paid = (float) $order->paid_amount;
        if (Schema::hasTable('payments')) {
            $paid = round((float) $order->payments()->where('status', 'CAPTURED')->sum('amount'), 2);
        }

        $balanceDue = max(0.0, round($total - $paid, 2));

        $order->update([
            'subtotal' => number_format($subtotal, 2, '.', ''),
            'discount_amount' => number_format($discountAmount, 2, '.', ''),
            'tax_amount' => number_format($taxAmount, 2, '.', ''),
            'total' => number_format($total, 2, '.', ''),
            'paid_amount' => number_format($paid, 2, '.', ''),
            'balance_due' => number_format($balanceDue, 2, '.', ''),
        ]);

        return $order->fresh();
    }
}
