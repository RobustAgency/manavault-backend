<?php

use App\Models\DigitalProduct;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropUnique('vouchers_code_unique');

            $table->foreignIdFor(DigitalProduct::class)
                ->nullable()
                ->after('purchase_order_item_id')
                ->constrained()
                ->nullOnDelete();

            $table->string('code_hash', 64)
                ->nullable()
                ->after('digital_product_id');

            $table->unique(['digital_product_id', 'code_hash'], 'vouchers_digital_product_code_hash_unique');
        });
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropUnique('vouchers_digital_product_code_hash_unique');
            $table->dropForeign(['digital_product_id']);
            $table->dropColumn(['digital_product_id', 'code_hash']);
            $table->string('code')->nullable()->unique()->change();
        });
    }
};
