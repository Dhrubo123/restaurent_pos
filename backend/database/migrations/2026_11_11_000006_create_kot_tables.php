<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('round_number');
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at');
            $table->timestamps();
            $table->unique(['order_id', 'round_number']);
        });

        Schema::create('kots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->restrictOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_round_id')->constrained()->cascadeOnDelete();
            $table->string('station', 20);
            $table->string('kot_number', 40);
            $table->string('status', 24)->default('PENDING');
            $table->unsignedSmallInteger('reprint_count')->default(0);
            $table->timestamp('last_reprinted_at')->nullable();
            $table->string('last_reprint_reason', 500)->nullable();
            $table->timestamp('sent_at');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamps();
            $table->unique(['restaurant_id', 'kot_number']);
            $table->unique(['order_round_id', 'station']);
            $table->index(['restaurant_id', 'station', 'status', 'sent_at']);
        });

        Schema::create('kot_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->restrictOnDelete();
            $table->foreignId('kot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained()->cascadeOnDelete();
            $table->string('sku', 40);
            $table->string('item_name', 120);
            $table->string('variant_name', 80)->nullable();
            $table->decimal('quantity', 10, 3);
            $table->decimal('cancelled_quantity', 10, 3)->default(0);
            $table->string('note', 300)->nullable();
            $table->json('modifiers_snapshot')->nullable();
            $table->string('status', 24)->default('PENDING');
            $table->string('cancel_reason', 500)->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('served_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->index(['kot_id', 'status']);
        });

        Schema::create('kot_status_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->restrictOnDelete();
            $table->foreignId('kot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('kot_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('from_status', 24)->nullable();
            $table->string('to_status', 24);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 500)->nullable();
            $table->timestamp('created_at');
            $table->index(['kot_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kot_status_events');
        Schema::dropIfExists('kot_items');
        Schema::dropIfExists('kots');
        Schema::dropIfExists('order_rounds');
    }
};
