<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->decimal('value', 13, 2);
            $table->decimal('computed_amount', 13, 2);
            $table->string('reason', 500);
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['order_id']);
        });

        Schema::create('cashier_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->restrictOnDelete();
            $table->foreignId('cashier_id')->constrained('users')->restrictOnDelete();
            $table->decimal('opening_float', 13, 2)->default(0);
            $table->decimal('expected_cash', 13, 2)->default(0);
            $table->decimal('counted_cash', 13, 2)->nullable();
            $table->decimal('variance', 13, 2)->nullable();
            $table->string('status', 24)->default('OPEN');
            $table->string('notes', 500)->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->index(['restaurant_id', 'cashier_id', 'status']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('cashier_shift_id')->nullable()->constrained()->nullOnDelete();
            $table->string('method', 24);
            $table->decimal('amount', 13, 2);
            $table->decimal('tendered_amount', 13, 2)->nullable();
            $table->decimal('change_given', 13, 2)->default(0);
            $table->string('status', 24)->default('CAPTURED');
            $table->string('provider_reference', 120)->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('captured_at');
            $table->timestamps();
            $table->index(['order_id', 'status']);
            $table->index(['cashier_shift_id', 'method', 'status']);
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->unique()->constrained()->restrictOnDelete();
            $table->string('invoice_number', 40);
            $table->string('status', 24)->default('ISSUED');
            $table->unsignedSmallInteger('guest_count')->default(1);
            $table->string('table_code', 30);
            $table->string('table_name', 80);
            $table->string('waiter_name', 120)->nullable();
            $table->string('cashier_name', 120)->nullable();
            $table->decimal('subtotal', 13, 2);
            $table->decimal('discount_amount', 13, 2)->default(0);
            $table->decimal('service_charge', 13, 2)->default(0);
            $table->decimal('tax_amount', 13, 2)->default(0);
            $table->decimal('total', 13, 2);
            $table->decimal('paid_amount', 13, 2);
            $table->decimal('tendered_cash', 13, 2)->default(0);
            $table->decimal('change_given', 13, 2)->default(0);
            $table->unsignedSmallInteger('reprint_count')->default(0);
            $table->timestamp('last_reprinted_at')->nullable();
            $table->string('last_reprint_reason', 500)->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at');
            $table->string('void_reason', 500)->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->json('payment_snapshot')->nullable();
            $table->timestamps();
            $table->unique(['restaurant_id', 'invoice_number']);
            $table->index(['restaurant_id', 'issued_at']);
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('sku', 40);
            $table->string('item_name', 120);
            $table->string('variant_name', 80)->nullable();
            $table->decimal('quantity', 10, 3);
            $table->decimal('unit_price', 13, 2);
            $table->decimal('modifier_unit_total', 13, 2)->default(0);
            $table->json('modifiers_snapshot')->nullable();
            $table->decimal('line_subtotal', 13, 2);
            $table->decimal('line_tax', 13, 2)->default(0);
            $table->decimal('line_total', 13, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('cashier_shifts');
        Schema::dropIfExists('discounts');
    }
};
