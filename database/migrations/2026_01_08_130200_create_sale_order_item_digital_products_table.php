<?php

use App\Models\SaleOrderItem;
use App\Models\DigitalProduct;
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
        Schema::create('sale_order_item_digital_products', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(SaleOrderItem::class)->constrained()->onDelete('cascade');
            $table->foreignIdFor(DigitalProduct::class)->constrained()->onDelete('cascade');
            $table->integer('quantity_deducted');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_order_item_digital_products');
    }
};
