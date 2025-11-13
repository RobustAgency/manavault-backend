<?php

use App\Models\Supplier;
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
        Schema::create('digital_products', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Supplier::class)->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('brand')->nullable();
            $table->text('description')->nullable();
            $table->json('tags')->nullable();
            $table->string('image')->nullable();
            $table->decimal('cost_price', 10, 2);
            $table->string('status')->default('active');
            $table->json('regions')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('digital_products');
    }
};
