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

            $table->dropForeign(['digital_product_id']);

            $table->unsignedBigInteger('digital_product_id')
                ->nullable()
                ->change();

            $table->foreign('digital_product_id')
                ->references('id')
                ->on('digital_products')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropForeign(['digital_product_id']);

            $table->foreign('digital_product_id')
                ->references('id')
                ->on('digital_products')
                ->onDelete('cascade');
        });
    }
};
