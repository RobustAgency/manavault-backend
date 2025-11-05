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
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('purchase_price', 10, 2)->after('description');
            $table->decimal('selling_price', 10, 2)->after('purchase_price');

            $table->dropColumn('price');
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->renameColumn('purchase_price', 'total_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->after('description');

            $table->dropColumn('purchase_price');
            $table->dropColumn('selling_price');
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->renameColumn('total_price', 'purchase_price');
        });
    }
};
