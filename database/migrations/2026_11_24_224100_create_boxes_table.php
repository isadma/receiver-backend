<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boxes', function (Blueprint $table) {
            $table->id();
            $table->string('shop_slug');
            $table->string('box_number');
            $table->string('status')->default('open');
            $table->timestamps();

            $table->unique(['shop_slug', 'box_number']);
            $table->index(['shop_slug', 'status']);
        });

        $pairs = DB::table('box_receipts')
            ->select('shop_slug', 'box_number')
            ->distinct()
            ->get();

        $now = now();
        foreach ($pairs as $pair) {
            DB::table('boxes')->insert([
                'shop_slug' => $pair->shop_slug,
                'box_number' => $pair->box_number,
                'status' => 'open',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('boxes');
    }
};
