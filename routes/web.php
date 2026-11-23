<?php

use App\Http\Controllers\ProductBoardController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ProductBoardController::class, 'index']);
Route::get('/export.csv', [ProductBoardController::class, 'export']);
