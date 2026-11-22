<?php

use App\Http\Controllers\Api\DemoController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ShopController;
use App\Http\Controllers\Api\StatusController;
use Illuminate\Support\Facades\Route;

Route::get('/', StatusController::class);
Route::get('/stats', [ProductController::class, 'stats']);
Route::get('/shops', [ShopController::class, 'index']);
Route::post('/demo/reset', [DemoController::class, 'reset']);

Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/sku/{sku}', [ProductController::class, 'showBySku']);
Route::get('/products/barcode/{barcode}', [ProductController::class, 'showByBarcode']);
Route::patch('/products/{sku}/barcode', [ProductController::class, 'updateBarcode']);
Route::post('/products/barcode/{barcode}/receive', [ProductController::class, 'receive']);
