<?php

use App\Enums\SaleOrderItemStatus;
use Illuminate\Support\Facades\DB;
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
        Schema::table('sale_order_items', function (Blueprint $table) {
            $table->string('status')->default(SaleOrderItemStatus::PENDING->value)->after('quantity')->index();
        });

        // Backfill items whose allocated vouchers already cover the ordered quantity. Done as raw
        // SQL so no model events fire and no webhooks are replayed for historical fulfillments.
        DB::table('sale_order_items')
            ->whereRaw('(
                select count(*)
                from sale_order_item_digital_products
                where sale_order_item_digital_products.sale_order_item_id = sale_order_items.id
                  and sale_order_item_digital_products.voucher_id is not null
            ) >= sale_order_items.quantity')
            ->update(['status' => SaleOrderItemStatus::COMPLETED->value]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sale_order_items', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });
    }
};
