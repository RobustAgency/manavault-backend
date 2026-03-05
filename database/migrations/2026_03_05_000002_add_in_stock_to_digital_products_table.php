<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('digital_products', function (Blueprint $table) {
            $table->boolean('in_stock')->default(true)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('digital_products', function (Blueprint $table) {
            $table->dropColumn('in_stock');
        });
    }
};
