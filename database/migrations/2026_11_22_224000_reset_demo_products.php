<?php

use App\Support\DemoCatalog;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DemoCatalog::reset();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Demo catalog reset is not reversible.
    }
};
