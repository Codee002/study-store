<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix("customer")->name("customer.")->group(function () {
    Route::prefix("auth")->name("auth.")->group(function () {
        Route::post("/register", [AuthController::class, 'registerCustomer'])->name("register");
        Route::post("/login", [AuthController::class, 'loginCustomer'])->name("login");
        Route::middleware('auth:sanctum')->group(function () {
            Route::get("/me", [AuthController::class, 'me'])->name("me");
        });
    });
});

Route::apiResource('categories', CategoryController::class);
Route::apiResource('suppliers', CategoryController::class);
Route::apiResource('tiers', CategoryController::class);
