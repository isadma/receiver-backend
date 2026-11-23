<?php

use App\Http\Controllers\Api\DemoController;
use App\Http\Controllers\Api\LotController;
use App\Http\Controllers\Api\ShopController;
use App\Http\Controllers\Api\StatusController;
use Illuminate\Support\Facades\Route;

Route::get('/', StatusController::class);
Route::get('/stats', [LotController::class, 'stats']);
Route::get('/shops', [ShopController::class, 'index']);
Route::post('/demo/reset', [DemoController::class, 'reset']);
Route::post('/catalog/import', [DemoController::class, 'import']);

Route::get('/lots', [LotController::class, 'index']);
Route::get('/lots/{lotBarcode}', [LotController::class, 'showByBarcode']);
Route::post('/lots/{lotBarcode}/receive', [LotController::class, 'receive']);
