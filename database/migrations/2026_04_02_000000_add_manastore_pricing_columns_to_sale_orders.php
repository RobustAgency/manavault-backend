<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_orders', function (Blueprint $table) {
            $table->string('currency')->nullable();
            $table->bigInteger('subtotal')->default(0)->after('source');
            $table->bigInteger('conversion_fees')->default(0)->after('subtotal');
        });

        Schema::table('sale_order_items', function (Blueprint $table) {
            $table->string('product_name')->nullable()->after('product_id');
            $table->bigInteger('conversion_fee')->default(0)->after('subtotal');
            $table->bigInteger('discount_amount')->default(0)->after('conversion_fee');
            $table->string('currency', 3)->default('USD')->after('discount_amount');
        });
    }

    public function down(): void
    {
        Schema::table('sale_orders', function (Blueprint $table) {
            $table->dropColumn(['currency', 'subtotal', 'conversion_fees']);
        });

        Schema::table('sale_order_items', function (Blueprint $table) {
            $table->dropColumn([
                'product_name',
                'conversion_fee',
                'discount_amount',
                'currency',
            ]);
        });
    }
};
