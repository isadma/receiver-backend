<?php

use App\Http\Controllers\Api\BoxController;
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
Route::post('/items/{barcode}/receive', [LotController::class, 'receiveItem']);

Route::get('/boxes', [BoxController::class, 'index']);
Route::post('/boxes', [BoxController::class, 'store']);
Route::post('/boxes/move-shop', [BoxController::class, 'moveShop']);
Route::get('/boxes/{shop}/{box}', [BoxController::class, 'show']);
Route::post('/boxes/{shop}/{box}/close', [BoxController::class, 'close']);
Route::post('/boxes/{shop}/{box}/open', [BoxController::class, 'open']);
Route::delete('/boxes/{shop}/{box}/lots/{lotId}', [BoxController::class, 'destroyLot']);
Route::patch('/box-receipts/{receipt}', [BoxController::class, 'updateReceipt']);
Route::delete('/box-receipts/{receipt}', [BoxController::class, 'destroyReceipt']);
