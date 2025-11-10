<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->boolean('voucher_codes_processed')->default(false)->after('total_price');
            $table->timestamp('voucher_codes_processed_at')->nullable()->after('voucher_codes_processed');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn(['voucher_codes_processed', 'voucher_codes_processed_at']);
        });
    }
};
