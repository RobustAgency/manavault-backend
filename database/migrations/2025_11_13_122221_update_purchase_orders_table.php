<?php

use App\Models\Product;
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
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropForeign(['product_id']);
            $table->dropColumn([
                'product_id',
                'voucher_codes_processed',
                'voucher_codes_processed_at',
                'quantity',
            ]);
            $table->string('status')->default('pending')->after('transaction_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreignIdFor(Product::class)->constrained()->after('id');
            $table->integer('quantity')->default(1)->after('total_price');
            $table->boolean('voucher_codes_processed')->default(false)->after('transaction_id');
            $table->dateTime('voucher_codes_processed_at')->nullable()->after('voucher_codes_processed');
            $table->dropColumn('status');
        });
    }
};
