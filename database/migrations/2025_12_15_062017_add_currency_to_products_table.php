<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('currency')->nullable()->after('selling_price');
        });

        Schema::table('digital_products', function (Blueprint $table) {
            $table->string('currency')->nullable()->after('cost_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('currency');
        });

        Schema::table('digital_products', function (Blueprint $table) {
            $table->dropColumn('currency');
        });
    }
};
