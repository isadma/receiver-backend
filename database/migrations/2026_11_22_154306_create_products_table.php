<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('barcode')->nullable();
            $table->string('sku')->nullable();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        $now = now();

        DB::table('products')->insert([
            ['barcode' => null, 'sku' => 'USB2866', 'name' => 'Toddler white T-shirt', 'created_at' => $now, 'updated_at' => $now],
            ['barcode' => null, 'sku' => 'USB3102', 'name' => 'Toddler navy shorts', 'created_at' => $now, 'updated_at' => $now],
            ['barcode' => null, 'sku' => 'USB4471', 'name' => 'Kids pink hoodie', 'created_at' => $now, 'updated_at' => $now],
            ['barcode' => null, 'sku' => 'USB1284', 'name' => 'Baby grey onesie', 'created_at' => $now, 'updated_at' => $now],
            ['barcode' => null, 'sku' => 'USB5590', 'name' => 'Toddler striped leggings', 'created_at' => $now, 'updated_at' => $now],
            ['barcode' => null, 'sku' => 'USB6723', 'name' => 'Kids blue jeans', 'created_at' => $now, 'updated_at' => $now],
            ['barcode' => null, 'sku' => 'USB8045', 'name' => 'Toddler yellow raincoat', 'created_at' => $now, 'updated_at' => $now],
            ['barcode' => null, 'sku' => 'USB2198', 'name' => 'Kids green polo shirt', 'created_at' => $now, 'updated_at' => $now],
            ['barcode' => null, 'sku' => 'USB9361', 'name' => 'Baby white socks', 'created_at' => $now, 'updated_at' => $now],
            ['barcode' => null, 'sku' => 'USB1547', 'name' => 'Toddler denim jacket', 'created_at' => $now, 'updated_at' => $now],
            ['barcode' => null, 'sku' => 'USB3680', 'name' => 'Kids red cardigan', 'created_at' => $now, 'updated_at' => $now],
            ['barcode' => null, 'sku' => 'USB7412', 'name' => 'Toddler floral dress', 'created_at' => $now, 'updated_at' => $now],
            ['barcode' => null, 'sku' => 'USB5829', 'name' => 'Kids black sweatpants', 'created_at' => $now, 'updated_at' => $now],
            ['barcode' => null, 'sku' => 'USB9034', 'name' => 'Baby beige romper', 'created_at' => $now, 'updated_at' => $now],
            ['barcode' => null, 'sku' => 'USB2765', 'name' => 'Toddler knitted beanie', 'created_at' => $now, 'updated_at' => $now],
            ['barcode' => null, 'sku' => 'USB6158', 'name' => 'Kids purple skirt', 'created_at' => $now, 'updated_at' => $now],
            ['barcode' => null, 'sku' => 'USB4207', 'name' => 'Toddler orange pajamas', 'created_at' => $now, 'updated_at' => $now],
            ['barcode' => null, 'sku' => 'USB8376', 'name' => 'Kids grey sweatshirt', 'created_at' => $now, 'updated_at' => $now],
            ['barcode' => null, 'sku' => 'USB1943', 'name' => 'Toddler corduroy overalls', 'created_at' => $now, 'updated_at' => $now],
            ['barcode' => null, 'sku' => 'USB7601', 'name' => 'Kids mint tank top', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
