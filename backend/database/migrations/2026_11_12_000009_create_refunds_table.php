<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('refunded_amount', 13, 2)->default(0)->after('paid_amount');
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('cashier_shift_id')->nullable()->constrained()->nullOnDelete();
            $table->string('refund_number', 40);
            $table->string('method', 24);
            $table->decimal('amount', 13, 2);
            $table->string('reason', 500);
            $table->string('provider_reference', 120)->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('refunded_at');
            $table->timestamps();

            $table->unique(['restaurant_id', 'refund_number']);
            $table->index(['restaurant_id', 'refunded_at']);
            $table->index(['invoice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('refunded_amount');
        });
    }
};
