<?php

use App\Models\Supplier;
use App\Models\PurchaseOrder;
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
        Schema::create('purchase_order_suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(PurchaseOrder::class)->constrained()->onDelete('cascade');
            $table->foreignIdFor(Supplier::class)->constrained()->onDelete('cascade');
            $table->string('transaction_id')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_order_suppliers');
    }
};
