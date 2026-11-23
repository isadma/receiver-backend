<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lots', function (Blueprint $table) {
            $table->id();
            $table->string('lot_barcode')->unique();
            $table->string('invoice_date')->nullable();
            $table->string('group_name')->nullable();
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->string('assortment')->nullable();
            $table->string('size_set')->nullable();
            $table->string('fabric_en')->nullable();
            $table->string('name_en')->nullable();
            $table->string('name_ru')->nullable();
            $table->string('color')->nullable();
            $table->string('color_en')->nullable();
            $table->string('variant')->nullable();
            $table->string('usd')->nullable();
            $table->string('currency')->nullable();
            $table->unsignedInteger('invoice_lot_qty')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lots');
    }
};
