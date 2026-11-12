<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->restrictOnDelete();
            $table->string('name', 80);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['restaurant_id', 'name']);
            $table->index(['restaurant_id', 'active', 'sort_order']);
        });

        Schema::create('dining_tables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->restrictOnDelete();
            $table->foreignId('zone_id')->constrained()->restrictOnDelete();
            $table->string('code', 30);
            $table->string('display_name', 80);
            $table->unsignedSmallInteger('capacity')->default(4);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['restaurant_id', 'code']);
            $table->unique(['restaurant_id', 'display_name']);
            $table->index(['zone_id', 'active', 'sort_order']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->restrictOnDelete();
            $table->foreignId('dining_table_id')->constrained()->restrictOnDelete();
            $table->string('order_number', 40);
            $table->string('status', 24)->default('OPEN');
            $table->foreignId('waiter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('guest_count')->default(1);
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->unique(['restaurant_id', 'order_number']);
            $table->index(['dining_table_id', 'status']);
            $table->index(['restaurant_id', 'opened_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
        Schema::dropIfExists('dining_tables');
        Schema::dropIfExists('zones');
    }
};
