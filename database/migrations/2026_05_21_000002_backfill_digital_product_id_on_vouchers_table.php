<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Subquery form works on both MySQL and SQLite
        DB::statement('
            UPDATE vouchers
            SET digital_product_id = (
                SELECT digital_product_id
                FROM purchase_order_items
                WHERE purchase_order_items.id = vouchers.purchase_order_item_id
            )
            WHERE purchase_order_item_id IS NOT NULL
              AND digital_product_id IS NULL
        ');
    }

    public function down(): void
    {
        DB::statement('UPDATE vouchers SET digital_product_id = NULL');
    }
};
