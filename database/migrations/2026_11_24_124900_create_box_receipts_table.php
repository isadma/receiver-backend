<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('box_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('shop_slug');
            $table->string('box_number');
            $table->foreignId('lot_id')->constrained('lots')->cascadeOnDelete();
            $table->foreignId('lot_item_id')->constrained('lot_items')->cascadeOnDelete();
            $table->unsignedInteger('qty')->default(0);
            $table->timestamps();

            $table->unique(['shop_slug', 'box_number', 'lot_item_id']);
            $table->index(['shop_slug', 'box_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('box_receipts');
    }
};
