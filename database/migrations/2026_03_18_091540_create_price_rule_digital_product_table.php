<?php

use App\Models\PriceRule;
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
        Schema::create('price_rule_digital_product', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(DigitalProduct::class)->constrained()->onDelete('cascade');
            $table->foreignIdFor(PriceRule::class)->constrained()->onDelete('cascade');
            $table->decimal('original_selling_price', 10, 2);
            $table->decimal('base_value', 10, 2);
            $table->string('action_mode');
            $table->string('action_operator');
            $table->decimal('action_value', 10, 2);
            $table->decimal('calculated_price', 10, 2);
            $table->decimal('final_selling_price', 10, 2);
            $table->timestamp('applied_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_rule_digital_product');
    }
};
