<?php

use App\Enums\Gift2GamesOrderStatus;
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
        Schema::create('gift2games_orders', function (Blueprint $table) {
            $table->id();
            $table->string('batch_number');
            $table->string('transaction_id')->nullable();
            $table->string('status')->default(Gift2GamesOrderStatus::PENDING);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gift2games_orders');
    }
};
