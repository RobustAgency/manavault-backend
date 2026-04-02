<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_orders', function (Blueprint $table) {
            $table->string('currency', 3)->default('USD')->change();
            $table->bigInteger('subtotal')->default(0)->after('source');
            $table->bigInteger('conversion_fees')->default(0)->after('subtotal');
            $table->bigInteger('total')->default(0)->after('conversion_fees');
        });

        Schema::table('sale_order_items', function (Blueprint $table) {
            $table->unsignedBigInteger('product_variant_id')->nullable()->after('product_id');
            $table->string('variant_name')->nullable()->after('product_variant_id');
            $table->string('product_name')->nullable()->after('variant_name');
            $table->bigInteger('price')->default(0)->after('subtotal');
            $table->bigInteger('purchase_price')->default(0)->after('price');
            $table->bigInteger('conversion_fee')->default(0)->after('purchase_price');
            $table->bigInteger('total_price')->default(0)->after('conversion_fee');
            $table->bigInteger('discount_amount')->default(0)->after('total_price');
            $table->string('currency', 3)->default('USD')->after('discount_amount');
            $table->string('status')->default('pending')->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('sale_orders', function (Blueprint $table) {
            $table->dropColumn(['subtotal', 'conversion_fees', 'total']);
        });

        Schema::table('sale_order_items', function (Blueprint $table) {
            $table->dropColumn([
                'product_variant_id',
                'variant_name',
                'product_name',
                'price',
                'purchase_price',
                'conversion_fee',
                'total_price',
                'discount_amount',
                'currency',
                'status',
            ]);
        });
    }
};
