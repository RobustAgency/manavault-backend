<?php

use App\Models\PriceRule;
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
        Schema::create('price_rule_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(PriceRule::class)->constrained()->onDelete('cascade');
            $table->string('field');
            $table->string('operator');
            $table->string('value');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_rule_conditions');
    }
};
