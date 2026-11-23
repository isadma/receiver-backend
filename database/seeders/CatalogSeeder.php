<?php

namespace Database\Seeders;

use App\Support\CatalogImporter;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        CatalogImporter::import(base_path('data.csv'));
    }
}
