<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_order_item_digital_products', function (Blueprint $table) {
            // Change digital_product_id to nullOnDelete so records survive if the digital product is deleted
            $table->dropForeign(['digital_product_id']);

            $table->unsignedBigInteger('digital_product_id')
                ->nullable()
                ->change();

            $table->foreign('digital_product_id')
                ->references('id')
                ->on('digital_products')
                ->nullOnDelete();

            // Snapshot of digital product details at the time of sale.
            // Preserved even if the digital product is later deleted.
            $table->string('digital_product_name')->nullable()->after('digital_product_id');
            $table->string('digital_product_sku')->nullable()->after('digital_product_name');
            $table->string('digital_product_brand')->nullable()->after('digital_product_sku');
        });
    }

    public function down(): void
    {
        Schema::table('sale_order_item_digital_products', function (Blueprint $table) {
            $table->dropColumn(['digital_product_name', 'digital_product_sku', 'digital_product_brand']);

            $table->dropForeign(['digital_product_id']);

            $table->unsignedBigInteger('digital_product_id')
                ->nullable(false)
                ->change();

            $table->foreign('digital_product_id')
                ->references('id')
                ->on('digital_products')
                ->onDelete('cascade');
        });
    }
};
