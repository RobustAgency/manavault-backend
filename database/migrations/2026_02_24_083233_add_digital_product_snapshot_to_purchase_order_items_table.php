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
        Schema::table('purchase_order_items', function (Blueprint $table) {
            // Snapshot of digital product details at the time of purchase.
            // Preserved even if the digital product is later deleted.
            $table->string('digital_product_name')->nullable()->after('digital_product_id');
            $table->string('digital_product_sku')->nullable()->after('digital_product_name');
            $table->string('digital_product_brand')->nullable()->after('digital_product_sku');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropColumn(['digital_product_name', 'digital_product_sku', 'digital_product_brand']);
        });
    }
};
