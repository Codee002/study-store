<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ColorController;
use App\Http\Controllers\Api\DeliveryInfoController;
use App\Http\Controllers\Api\DiscountController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PriceQuotationController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ReceiptController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\TierController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WarehouseController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ProductStatsController;
use App\Http\Controllers\Api\NotificationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

Broadcast::routes(['middleware' => ['auth:sanctum']]);

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('customer')->name('customer.')->group(function () {
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('/register', [AuthController::class, 'registerCustomer'])->name('register');
        Route::post('/login', [AuthController::class, 'loginCustomer'])->name('login');
        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/me', [AuthController::class, 'me'])->name('me');
            Route::post('/change-password', [AuthController::class, 'changePassword'])->name('change-password');
            Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        });
    });
});

Route::prefix('admin')->name('admin.')->group(function () {
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('/login', [AuthController::class, 'loginAdmin'])->name('login');
        Route::middleware('auth:sanctum')->group(function () {
            Route::get('/me', [AuthController::class, 'me'])->name('me');
            Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        });
    });
});

Route::get('/vnpay/return', [OrderController::class, 'vnpayReturn']);
Route::get('/vnpay/ipn', [OrderController::class, 'vnpayIpn']);

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('categories', CategoryController::class);
    Route::apiResource('suppliers', SupplierController::class);
    Route::apiResource('tiers', TierController::class);
    Route::apiResource('carts', CartController::class);
    Route::get('price-quotations/admin-export', [PriceQuotationController::class, 'adminExport']);
    Route::get('price-quotations/my-export', [PriceQuotationController::class, 'myExport']);
    Route::post('price-quotations/validate-purchase-file', [PriceQuotationController::class, 'validatePurchaseFile']);

    // Products
    Route::post('products/{id}/save-product-prices', [ProductController::class, 'saveProductPrices']);
    Route::get('products/get-home-products', [ProductController::class, 'getHomeProducts']);
    Route::get('products/{id}/customer-detail', [ProductController::class, 'getCustomerProductDetail']);
    Route::get('products/{id}/reviews', [ProductController::class, 'getCustomerProductReviews']);
    Route::apiResource('products', ProductController::class);

    Route::apiResource('payments', PaymentController::class);
    Route::apiResource('colors', ColorController::class);
    Route::apiResource('discounts', DiscountController::class);

    // Profiles
    Route::get('profile', [ProfileController::class, 'show']);
    Route::post('profile', [ProfileController::class, 'update']);

    // Dealer
    Route::get('dealer-registration/meta', [ProfileController::class, 'dealerRegistrationMeta']);
    Route::post('dealer-registration', [ProfileController::class, 'registerDealer']);

    // Delivery
    Route::get('delivery-infos', [DeliveryInfoController::class, 'index']);
    Route::get('delivery-infos/{id}', [DeliveryInfoController::class, 'show']);
    Route::post('delivery-infos', [DeliveryInfoController::class, 'store']);
    Route::patch('delivery-infos/{id}', [DeliveryInfoController::class, 'update']);
    Route::patch('delivery-infos/{id}/set-default', [DeliveryInfoController::class, 'setDefault']);
    Route::delete('delivery-infos/{id}', [DeliveryInfoController::class, 'destroy']);

    // Orther
    Route::post('checkout/options', [OrderController::class, 'checkoutOptions']);
    Route::post('checkout/vnpay/create', [OrderController::class, 'createVNPayPayment']);
    Route::get('checkout/vnpay/status', [OrderController::class, 'vnpayStatus']);
    Route::post('checkout/place', [OrderController::class, 'placeOrder']);
    Route::get('orders/my', [OrderController::class, 'myOrders']);
    Route::get('orders/my/{id}', [OrderController::class, 'myOrderDetail']);
    Route::post('orders/my/{id}/cancel', [OrderController::class, 'cancelMyOrder']);
    Route::post('orders/my/{id}/complete', [OrderController::class, 'completeMyOrder']);
    Route::post('orders/my/{id}/evaluate', [OrderController::class, 'submitMyOrderEvaluate']);
    Route::get('orders/admin-create-meta', [OrderController::class, 'adminCreateMeta']);
    Route::post('orders', [OrderController::class, 'adminCreateOrder']);
    Route::get('orders', [OrderController::class, 'adminOrders']);
    Route::get('orders/{id}', [OrderController::class, 'adminOrderDetail']);
    Route::post('orders/{id}/approve', [OrderController::class, 'approveOrder']);
    Route::post('orders/{id}/reject', [OrderController::class, 'rejectOrder']);

    // Messages (customer -> admin)
    Route::prefix('customer/messages')->name('customer.messages.')->group(function () {
        Route::post('/start', [MessageController::class, 'ensureCustomerConversation']);
        Route::get('/{conversation}', [MessageController::class, 'fetchMessages']);
        Route::post('/{conversation}/send', [MessageController::class, 'send']);
        Route::post('/{conversation}/messages/{message}/recall', [MessageController::class, 'recall']);
    });

    // Customer notifications
    Route::prefix('customer/notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::post('/{notification}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/read-all', [NotificationController::class, 'markAllAsRead']);
    });

    // Messages (admin -> customer)
    Route::prefix('admin/messages')->name('admin.messages.')->group(function () {
        Route::get('/contacts', [MessageController::class, 'adminContacts']);
        Route::post('/with/{customer}', [MessageController::class, 'ensureAdminConversation']);
        Route::get('/{conversation}', [MessageController::class, 'fetchMessages']);
        Route::post('/{conversation}/send', [MessageController::class, 'send']);
        Route::post('/{conversation}/messages/{message}/recall', [MessageController::class, 'recall']);
    });

    // Admin notifications
    Route::prefix('admin/notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::post('/{notification}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/read-all', [NotificationController::class, 'markAllAsRead']);
    });

    // Users
    Route::get('users', [UserController::class, 'index']);
    Route::get('users/{id}', [UserController::class, 'show']);
    Route::post('users/{id}/set-tier', [UserController::class, 'setTier']);
    Route::post('users/{id}/dealer-profile/status', [UserController::class, 'updateDealerStatus']);
    Route::post('users/{id}/status', [UserController::class, 'updateStatus']);

    // Dashboard
    Route::get('admin/dashboard/summary', [DashboardController::class, 'summary']);
    Route::get('admin/product-stats', [ProductStatsController::class, 'index']);
    Route::get('admin/product-stats/export', [ProductStatsController::class, 'export']);

    // Warehouse
    Route::get('warehouses/get-product-total-quantity', [WarehouseController::class, 'getProductTotalQuantity']);
    Route::get('warehouses/{id}/details', [WarehouseController::class, 'details']);
    Route::patch('warehouses/{warehouseDetailId}/toggle-status', [WarehouseController::class, 'toggleStatus']);
    Route::apiResource('warehouses', WarehouseController::class)->whereNumber('warehouse');

    // Receipts
    Route::apiResource('receipts', ReceiptController::class);
    Route::post('receipts/{id}/approve', [ReceiptController::class, 'approve']);
    Route::post('receipts/{id}/reject', [ReceiptController::class, 'reject']);
});
