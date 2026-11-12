<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('note', 500)->nullable()->after('version');
            $table->decimal('subtotal', 13, 2)->default(0)->after('note');
            $table->decimal('discount_amount', 13, 2)->default(0)->after('subtotal');
            $table->decimal('service_charge', 13, 2)->default(0)->after('discount_amount');
            $table->decimal('tax_amount', 13, 2)->default(0)->after('service_charge');
            $table->decimal('total', 13, 2)->default(0)->after('tax_amount');
            $table->decimal('paid_amount', 13, 2)->default(0)->after('total');
            $table->decimal('balance_due', 13, 2)->default(0)->after('paid_amount');
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('item_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sku', 40);
            $table->string('item_name', 120);
            $table->string('variant_name', 80)->nullable();
            $table->string('station', 20)->default('KITCHEN');
            $table->decimal('unit_price', 13, 2);
            $table->decimal('modifier_unit_total', 13, 2)->default(0);
            $table->decimal('tax_rate', 6, 3)->default(0);
            $table->boolean('tax_inclusive')->default(false);
            $table->decimal('quantity', 10, 3)->default(1);
            $table->decimal('sent_quantity', 10, 3)->default(0);
            $table->decimal('cancelled_quantity', 10, 3)->default(0);
            $table->decimal('served_quantity', 10, 3)->default(0);
            $table->decimal('line_subtotal', 13, 2)->default(0);
            $table->decimal('line_tax', 13, 2)->default(0);
            $table->decimal('line_total', 13, 2)->default(0);
            $table->string('note', 300)->nullable();
            $table->string('status', 24)->default('DRAFT');
            $table->string('cancel_reason', 300)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->index(['order_id', 'status']);
        });

        Schema::create('order_item_modifiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('modifier_option_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 80);
            $table->decimal('unit_price', 13, 2)->default(0);
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->timestamps();
        });

        Schema::create('table_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('from_dining_table_id')->constrained('dining_tables')->restrictOnDelete();
            $table->foreignId('to_dining_table_id')->constrained('dining_tables')->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 500);
            $table->timestamp('transferred_at');
            $table->timestamps();
        });

        Schema::create('order_merges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->restrictOnDelete();
            $table->foreignId('source_order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignId('target_order_id')->constrained('orders')->restrictOnDelete();
            $table->foreignId('from_dining_table_id')->constrained('dining_tables')->restrictOnDelete();
            $table->foreignId('to_dining_table_id')->constrained('dining_tables')->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 500);
            $table->json('snapshot')->nullable();
            $table->timestamp('merged_at');
            $table->timestamps();
        });

        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('scope', 100);
            $table->string('idempotency_key', 100);
            $table->string('request_hash', 64);
            $table->unsignedSmallInteger('status_code');
            $table->json('response_body');
            $table->timestamp('created_at');
            $table->unique(['restaurant_id', 'user_id', 'scope', 'idempotency_key'], 'idempotency_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
        Schema::dropIfExists('order_merges');
        Schema::dropIfExists('table_transfers');
        Schema::dropIfExists('order_item_modifiers');
        Schema::dropIfExists('order_items');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'note', 'subtotal', 'discount_amount', 'service_charge',
                'tax_amount', 'total', 'paid_amount', 'balance_due',
            ]);
        });
    }
};
