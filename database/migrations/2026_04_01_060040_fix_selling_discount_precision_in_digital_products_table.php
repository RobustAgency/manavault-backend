<?php

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
        DB::table('digital_products')->whereNull('selling_discount')->update(['selling_discount' => 0]);
        Schema::table('digital_products', function (Blueprint $table) {
            $table->decimal('selling_discount', 5, 2)->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('digital_products', function (Blueprint $table) {
            $table->decimal('selling_discount', 5, 2)->default(0)->change();
        });
    }
};
