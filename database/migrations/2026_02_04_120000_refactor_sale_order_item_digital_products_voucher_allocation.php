<?php

use App\Models\Voucher;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_order_item_digital_products', function (Blueprint $table) {
            $table->foreignIdFor(Voucher::class)
                ->nullable()
                ->after('digital_product_id')
                ->constrained()
                ->nullOnDelete();

            $table->unique('voucher_id', 'soidp_voucher_id_unique');

            $table->dropColumn('quantity_deducted');
        });
    }

    public function down(): void
    {
        Schema::table('sale_order_item_digital_products', function (Blueprint $table) {
            $table->dropForeign(['voucher_id']);
            $table->dropUnique('soidp_voucher_id_unique');
            $table->dropColumn('voucher_id');

            $table->integer('quantity_deducted')
                ->default(0)
                ->after('digital_product_id');
        });
    }
};
