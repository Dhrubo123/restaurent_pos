<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->restrictOnDelete();
            $table->string('name', 80);
            $table->decimal('rate', 6, 3)->default(0);
            $table->boolean('inclusive')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_to')->nullable();
            $table->timestamps();
            $table->unique(['restaurant_id', 'name']);
        });

        Schema::create('menu_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->restrictOnDelete();
            $table->string('name', 80);
            $table->string('default_station', 20)->default('KITCHEN');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['restaurant_id', 'name']);
            $table->index(['restaurant_id', 'active', 'sort_order']);
        });

        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->restrictOnDelete();
            $table->foreignId('menu_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('tax_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sku', 40);
            $table->string('name', 120);
            $table->string('description', 500)->nullable();
            $table->string('image_path', 255)->nullable();
            $table->string('station', 20)->default('KITCHEN');
            $table->decimal('price', 13, 2);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->boolean('available')->default(true);
            $table->timestamps();
            $table->unique(['restaurant_id', 'sku']);
            $table->unique(['restaurant_id', 'menu_category_id', 'name']);
            $table->index(['restaurant_id', 'active', 'available', 'sort_order']);
        });

        Schema::create('item_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->restrictOnDelete();
            $table->foreignId('menu_item_id')->constrained()->cascadeOnDelete();
            $table->string('sku', 40);
            $table->string('name', 80);
            $table->decimal('price', 13, 2);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['restaurant_id', 'sku']);
            $table->unique(['menu_item_id', 'name']);
        });

        Schema::create('modifier_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->restrictOnDelete();
            $table->string('name', 80);
            $table->unsignedSmallInteger('min_selections')->default(0);
            $table->unsignedSmallInteger('max_selections')->default(1);
            $table->boolean('required')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['restaurant_id', 'name']);
        });

        Schema::create('modifier_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->restrictOnDelete();
            $table->foreignId('modifier_group_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->decimal('price', 13, 2)->default(0);
            $table->string('station_override', 20)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['modifier_group_id', 'name']);
        });

        Schema::create('item_modifier_groups', function (Blueprint $table) {
            $table->foreignId('menu_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('modifier_group_id')->constrained()->cascadeOnDelete();
            $table->primary(['menu_item_id', 'modifier_group_id']);
        });

        Schema::create('price_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->restrictOnDelete();
            $table->string('priceable_type', 120);
            $table->unsignedBigInteger('priceable_id');
            $table->decimal('amount', 13, 2);
            $table->timestamp('effective_from');
            $table->timestamp('effective_to')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 500)->nullable();
            $table->timestamps();
            $table->index(['priceable_type', 'priceable_id', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_histories');
        Schema::dropIfExists('item_modifier_groups');
        Schema::dropIfExists('modifier_options');
        Schema::dropIfExists('modifier_groups');
        Schema::dropIfExists('item_variants');
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('menu_categories');
        Schema::dropIfExists('tax_categories');
    }
};
