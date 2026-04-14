<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProductController;


Route::post('/login', [\App\Http\Controllers\Api\AuthController::class, 'login']);

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware('auth:sanctum')->group(function () {         
    Route::post('/logout', [\App\Http\Controllers\Api\AuthController::class, 'logout']);
    Route::get('/dashboard', [\App\Http\Controllers\Api\PegawaiController::class, 'index'])->name('dashboard');
    Route::resource('/cart', \App\Http\Controllers\Api\CartController::class);
});
    Route::get('/produk', [\App\Http\Controllers\Api\PegawaiController::class, 'getProdukApi'])->name('produk');