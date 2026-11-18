<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->string('station', 20)->nullable()->after('variant_name');
        });

        DB::table('invoice_items')->orderBy('id')->chunkById(500, function ($invoiceItems): void {
            foreach ($invoiceItems as $invoiceItem) {
                $orderId = DB::table('invoices')->where('id', $invoiceItem->invoice_id)->value('order_id');
                $stations = DB::table('order_items')
                    ->where('order_id', $orderId)
                    ->where('sku', $invoiceItem->sku)
                    ->where('item_name', $invoiceItem->item_name)
                    ->where('variant_name', $invoiceItem->variant_name)
                    ->distinct()
                    ->pluck('station');

                if ($stations->count() === 1 && in_array($stations->first(), ['KITCHEN', 'BAR'], true)) {
                    DB::table('invoice_items')->where('id', $invoiceItem->id)->update(['station' => $stations->first()]);
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn('station');
        });
    }
};
