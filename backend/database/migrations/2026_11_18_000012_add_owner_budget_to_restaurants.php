<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            if (! Schema::hasColumn('restaurants', 'owner_monthly_budget')) {
                $table->decimal('owner_monthly_budget', 12, 2)->default(20000.00)->after('currency_code');
            }
        });
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            if (Schema::hasColumn('restaurants', 'owner_monthly_budget')) {
                $table->dropColumn('owner_monthly_budget');
            }
        });
    }
};
