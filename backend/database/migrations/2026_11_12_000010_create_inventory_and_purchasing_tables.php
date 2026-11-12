<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->restrictOnDelete();
            $table->string('name', 120);
            $table->string('contact_person', 120)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email', 120)->nullable();
            $table->string('address', 300)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['restaurant_id', 'name']);
        });

        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->restrictOnDelete();
            $table->string('sku', 40);
            $table->string('name', 120);
            $table->string('category', 80)->default('General');
            $table->string('unit', 24)->default('pcs');
            $table->decimal('current_stock', 13, 3)->default(0);
            $table->decimal('reorder_level', 13, 3)->default(0);
            $table->decimal('unit_cost', 13, 2)->default(0);
            $table->boolean('auto_eighty_six')->default(true);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['restaurant_id', 'sku']);
            $table->index(['restaurant_id', 'active']);
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('po_number', 40);
            $table->string('reference_number', 80)->nullable();
            $table->string('status', 24)->default('RECEIVED');
            $table->decimal('subtotal', 13, 2);
            $table->decimal('tax_amount', 13, 2)->default(0);
            $table->decimal('total', 13, 2);
            $table->string('notes', 500)->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at');
            $table->timestamps();

            $table->unique(['restaurant_id', 'po_number']);
            $table->index(['restaurant_id', 'received_at']);
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 13, 3);
            $table->decimal('unit_cost', 13, 2);
            $table->decimal('line_total', 13, 2);
            $table->timestamps();
        });

        Schema::create('menu_item_ingredients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->restrictOnDelete();
            $table->foreignId('menu_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 13, 3);
            $table->timestamps();

            $table->unique(['menu_item_id', 'inventory_item_id'], 'menu_item_ing_unique');
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->restrictOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->restrictOnDelete();
            $table->string('type', 32);
            $table->decimal('quantity_delta', 13, 3);
            $table->decimal('stock_after', 13, 3);
            $table->decimal('unit_cost', 13, 2)->nullable();
            $table->string('reference_type', 60)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reason', 500)->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['restaurant_id', 'occurred_at']);
            $table->index(['inventory_item_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('menu_item_ingredients');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('inventory_items');
        Schema::dropIfExists('suppliers');
    }
};
