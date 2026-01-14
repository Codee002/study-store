<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ColorController;
use App\Http\Controllers\Api\DiscountController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ReceiptController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\TierController;
use App\Http\Controllers\Api\WarehouseController;
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
            Route::post("/logout", [AuthController::class, 'logout'])->name("logout");
        });
    });
});

Route::prefix("admin")->name("admin.")->group(function () {
    Route::prefix("auth")->name("auth.")->group(function () {
        Route::post("/login", [AuthController::class, 'loginAdmin'])->name("login");
        Route::middleware('auth:sanctum')->group(function () {
            Route::get("/me", [AuthController::class, 'me'])->name("me");
            Route::post("/logout", [AuthController::class, 'logout'])->name("logout");
        });
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('categories', CategoryController::class);
    Route::apiResource('suppliers', SupplierController::class);
    Route::apiResource('tiers', TierController::class);
    Route::apiResource('products', ProductController::class);
    Route::apiResource('payments', PaymentController::class);
    Route::apiResource('colors', ColorController::class);
    Route::apiResource('discounts', DiscountController::class);

    // Kho hàng
    Route::apiResource('warehouses', WarehouseController::class);
    Route::get('warehouses/{id}/details', [WarehouseController::class, 'details']);
    Route::patch('warehouse/{warehouseDetailId}/toggle-status', [WarehouseController::class, 'toggleStatus']);

    // Phiếu nhập
    Route::apiResource('receipts', ReceiptController::class);
    Route::post('receipts/{id}/approve', [ReceiptController::class, 'approve']);
    Route::post('receipts/{id}/reject', [ReceiptController::class, 'reject']);
});
