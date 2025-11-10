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
        Schema::table('vouchers', function (Blueprint $table) {
            $table->string('status')->default('COMPLETED')->after('code');
            $table->string('serial_number')->nullable()->after('code');
            $table->string('pin_code')->nullable()->after('serial_number');
            $table->string('code')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropColumn('status');
            $table->dropColumn('serial_number');
            $table->dropColumn('pin_code');
            $table->string('code')->nullable(false)->change();
        });
    }
};
