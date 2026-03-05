<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('digital_products', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('last_synced_at');
        });
    }

    public function down(): void
    {
        Schema::table('digital_products', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
