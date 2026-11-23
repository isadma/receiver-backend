<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\CatalogImporter;
use Illuminate\Http\JsonResponse;

class DemoController extends Controller
{
    public function reset(): JsonResponse
    {
        CatalogImporter::resetReceived();

        return response()->json([
            'data' => [
                'reset' => true,
            ],
        ]);
    }
}
