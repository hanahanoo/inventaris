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
    Route::get('/profile', [\App\Http\Controllers\Api\AuthController::class, 'profile']);
    Route::get('/dashboard', [\App\Http\Controllers\Api\PegawaiController::class, 'index'])->name('dashboard');
    Route::get('/produk', [\App\Http\Controllers\Api\PegawaiController::class, 'getProdukApi'])->name('produk');
    Route::get('/history', [\App\Http\Controllers\Api\CartController::class, 'history'])->name('history');
    Route::post('/cart/{id}/submit', [\App\Http\Controllers\Api\CartController::class, 'submit'])->name('cart.submit');
    Route::resource('/cart', \App\Http\Controllers\Api\CartController::class);
});
