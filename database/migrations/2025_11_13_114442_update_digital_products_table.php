<?php

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
        Schema::table('digital_products', function (Blueprint $table) {
            $table->dropColumn([
                'tags',
                'image',
                'regions',
            ]);
        });

        Schema::table('digital_products', function (Blueprint $table) {
            $table->string('sku', 255)->nullable();
            $table->dateTime('last_synced_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('digital_products', function (Blueprint $table) {
            $table->json('tags')->nullable();
            $table->string('image')->nullable();
            $table->json('regions')->nullable();
        });

        Schema::table('digital_products', function (Blueprint $table) {
            $table->dropColumn([
                'sku',
                'last_synced_at',
            ]);
        });
    }
};
