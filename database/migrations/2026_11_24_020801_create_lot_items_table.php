<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lot_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lot_id')->constrained('lots')->cascadeOnDelete();
            $table->string('barcode')->unique();
            $table->string('size_code');
            $table->unsignedInteger('pieces_per_lot');
            $table->unsignedInteger('invoice_qty')->default(0);
            $table->unsignedInteger('amazon_received_qty')->default(0);
            $table->unsignedInteger('noon_received_qty')->default(0);
            $table->unsignedInteger('namshi_received_qty')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lot_items');
    }
};
