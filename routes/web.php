<?php

use App\Http\Controllers\ProductBoardController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ProductBoardController::class, 'index']);
Route::get('/datatable', [ProductBoardController::class, 'datatable']);
Route::get('/export.xlsx', [ProductBoardController::class, 'export']);
Route::get('/export.csv', fn () => redirect('/export.xlsx'));
