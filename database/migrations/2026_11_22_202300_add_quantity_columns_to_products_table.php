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
            $table->unsignedInteger('total_expected_qty')->default(0);
            $table->unsignedInteger('amazon_expected_qty')->default(0);
            $table->unsignedInteger('noon_expected_qty')->default(0);
            $table->unsignedInteger('namshi_expected_qty')->default(0);
            $table->unsignedInteger('total_received_qty')->default(0);
            $table->unsignedInteger('amazon_received_qty')->default(0);
            $table->unsignedInteger('noon_received_qty')->default(0);
            $table->unsignedInteger('namshi_received_qty')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'total_expected_qty',
                'amazon_expected_qty',
                'noon_expected_qty',
                'namshi_expected_qty',
                'total_received_qty',
                'amazon_received_qty',
                'noon_received_qty',
                'namshi_received_qty',
            ]);
        });
    }
};
