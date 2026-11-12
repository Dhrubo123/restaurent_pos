<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_subcategories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->restrictOnDelete();
            $table->foreignId('menu_category_id')->constrained()->restrictOnDelete();
            $table->string('name', 80);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['menu_category_id', 'name']);
            $table->index(['restaurant_id', 'menu_category_id', 'active', 'sort_order'], 'menu_subcategories_lookup_index');
        });

        Schema::table('menu_items', function (Blueprint $table) {
            $table->foreignId('menu_subcategory_id')
                ->nullable()
                ->after('menu_category_id')
                ->constrained('menu_subcategories')
                ->nullOnDelete();
        });

        Schema::table('item_variants', function (Blueprint $table) {
            $table->boolean('available')->default(true)->after('active');
        });

        Schema::table('modifier_options', function (Blueprint $table) {
            $table->boolean('available')->default(true)->after('active');
        });
    }

    public function down(): void
    {
        Schema::table('modifier_options', function (Blueprint $table) {
            $table->dropColumn('available');
        });

        Schema::table('item_variants', function (Blueprint $table) {
            $table->dropColumn('available');
        });

        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('menu_subcategory_id');
        });

        Schema::dropIfExists('menu_subcategories');
    }
};
