<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ItemController;

Route::post('/login', [AuthController::class, 'login']);

Route::get('/items', [ItemController::class, 'index']);

Route::middleware(\App\Http\Middleware\AuthenticateToken::class)->group(function () {
    Route::post('/items', [ItemController::class, 'store']);
    Route::put('/items/{id}', [ItemController::class, 'update']);
    Route::delete('/items/{id}', [ItemController::class, 'destroy']);
});
