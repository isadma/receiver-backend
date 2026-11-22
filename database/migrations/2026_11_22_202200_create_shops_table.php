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
        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        $now = now();

        DB::table('shops')->insert([
            ['name' => 'Amazon', 'slug' => 'amazon', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Noon', 'slug' => 'noon', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Namshi', 'slug' => 'namshi', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shops');
    }
};
