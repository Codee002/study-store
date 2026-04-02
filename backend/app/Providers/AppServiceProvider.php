<?php

namespace App\Providers;

use App\Models\Evaluate;
use App\Models\Order;
use App\Models\Price;
use App\Models\Product;
use App\Models\WarehouseDetail;
use App\Observers\EvaluateObserver;
use App\Observers\OrderObserver;
use App\Observers\PriceObserver;
use App\Observers\ProductObserver;
use App\Observers\ReceiptObserver;
use App\Observers\WarehouseDetailObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Evaluate::observe(EvaluateObserver::class);
        Order::observe(OrderObserver::class);
        \App\Models\Receipt::observe(ReceiptObserver::class);
        Product::observe(ProductObserver::class);
        Price::observe(PriceObserver::class);
        WarehouseDetail::observe(WarehouseDetailObserver::class);
    }
}
