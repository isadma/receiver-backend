<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\DemoCatalog;
use Illuminate\Http\JsonResponse;

class DemoController extends Controller
{
    public function reset(): JsonResponse
    {
        DemoCatalog::reset();

        return response()->json([
            'data' => [
                'reset' => true,
                'products' => count(DemoCatalog::definitions()),
            ],
        ]);
    }
}
