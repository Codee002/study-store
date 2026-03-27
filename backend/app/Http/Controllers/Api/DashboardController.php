<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\Evaluate;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\ReceiptDetail;
use App\Models\User;
use App\Models\WarehouseDetail;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    private function ensureAdmin(Request $request): void
    {
        $user = $request->user();
        if (! $user || (string) ($user->role ?? '') !== 'admin') {
            abort(403, 'Khong co quyen truy cap');
        }
    }

    public function summary(Request $request)
    {
        $this->ensureAdmin($request);

        $today       = Carbon::today();
        $date        = $request->query('date')
            ? Carbon::parse($request->query('date'))->startOfDay()
            : $today;
        $rangeDays   = max(7, min((int) ($request->query('range_days', 30)), 90)); // clamp 7-90
        $topDays     = max(1, min((int) ($request->query('top_days', 7)), 30));
        $lowStockThr = max(1, (int) ($request->query('low_stock_threshold', 100)));

        $rangeStart  = $date->copy()->subDays($rangeDays - 1)->startOfDay();
        $topStart    = $date->copy()->subDays($topDays - 1)->startOfDay();
        $rangeEnd    = $date->copy()->endOfDay();

        try {
            $ordersToday = Order::query()
                ->whereBetween('created_at', [$date, $rangeEnd])
                ->count();

            $completedToday = Order::query()
                ->where('status', 'completed')
                ->whereBetween('created_at', [$date, $rangeEnd])
                ->pluck('id')
                ->all();

            $revenueToday = OrderDetail::query()
                ->whereIn('order_id', $completedToday)
                ->select(DB::raw('SUM(quantity * price) as revenue'))
                ->value('revenue') ?? 0;

            $productsSoldToday = OrderDetail::query()
                ->whereIn('order_id', $completedToday)
                ->sum('quantity');

            $receiptIds = Receipt::query()
                ->where('status', 'completed')
                ->whereBetween('created_at', [$date, $rangeEnd])
                ->pluck('id')
                ->all();

            $receiptValueToday = ReceiptDetail::query()
                ->whereIn('receipt_id', $receiptIds)
                ->select(DB::raw('SUM(quantity * purchase_price) as total'))
                ->value('total') ?? 0;

            $totalProducts = Product::query()->count();
            $totalUsers    = User::query()->where('role', 'user')->count();

            $ordersByStatus = Order::query()
                ->select('status', DB::raw('COUNT(*) as total'))
                ->whereBetween('created_at', [$rangeStart, $rangeEnd])
                ->groupBy('status')
                ->pluck('total', 'status')
                ->map(fn ($v) => (int) $v)
                ->toArray();

            $statusList = ['pending', 'shipping', 'completed', 'cancelled', 'rejected'];
            $orderStatusCounts = [];
            foreach ($statusList as $status) {
                $orderStatusCounts[$status] = (int) ($ordersByStatus[$status] ?? 0);
            }
            $totalOrdersRange = array_sum($orderStatusCounts);

            $completedIds30d = Order::query()
                ->where('status', 'completed')
                ->whereBetween('created_at', [$rangeStart, $rangeEnd])
                ->pluck('id')
                ->all();

            $completedIds7d = Order::query()
                ->where('status', 'completed')
                ->whereBetween('created_at', [$topStart, $rangeEnd])
                ->pluck('id')
                ->all();

            $revenueLast7Days = OrderDetail::query()
                ->whereIn('order_id', $completedIds7d)
                ->select(DB::raw('SUM(quantity * price) as revenue'))
                ->value('revenue') ?? 0;

            $revenueLast30Days = OrderDetail::query()
                ->whereIn('order_id', $completedIds30d)
                ->select(DB::raw('SUM(quantity * price) as revenue'))
                ->value('revenue') ?? 0;

            $quantitySold30d = OrderDetail::query()
                ->whereIn('order_id', $completedIds30d)
                ->sum('quantity');

            $purchases30d = ReceiptDetail::query()
                ->join('receipts', 'receipts.id', '=', 'receipt_details.receipt_id')
                ->where('receipts.status', 'completed')
                ->whereBetween('receipts.created_at', [$rangeStart, $rangeEnd])
                ->select([
                    DB::raw('SUM(receipt_details.quantity) as total_quantity'),
                    DB::raw('SUM(receipt_details.quantity * receipt_details.purchase_price) as total_cost'),
                ])
                ->first();

            $purchases7d = ReceiptDetail::query()
                ->join('receipts', 'receipts.id', '=', 'receipt_details.receipt_id')
                ->where('receipts.status', 'completed')
                ->whereBetween('receipts.created_at', [$topStart, $rangeEnd])
                ->select([
                    DB::raw('SUM(receipt_details.quantity) as total_quantity'),
                    DB::raw('SUM(receipt_details.quantity * receipt_details.purchase_price) as total_cost'),
                ])
                ->first();

            $topProducts7d = OrderDetail::query()
                ->select([
                    'order_details.product_id',
                    'products.name',
                    DB::raw('COALESCE(MIN(pi.url), "") as image_url'),
                    DB::raw('SUM(order_details.quantity) as total_qty'),
                    DB::raw('SUM(order_details.quantity * order_details.price) as total_revenue'),
                ])
                ->join('orders', 'orders.id', '=', 'order_details.order_id')
                ->join('products', 'products.id', '=', 'order_details.product_id')
                ->leftJoin('product_images as pi', 'pi.product_id', '=', 'products.id')
                ->where('orders.status', 'completed')
                ->groupBy('order_details.product_id', 'products.name')
                ->orderByDesc('total_qty')
                ->limit(5)
                ->get()
                ->map(function ($row) {
                    return [
                        'product_id' => (int) $row->product_id,
                        'name'       => (string) ($row->name ?? ''),
                        'image_url'  => (string) $row->image_url,
                        'total_qty'  => (int) $row->total_qty,
                        'revenue'    => round((float) $row->total_revenue, 2),
                    ];
                })
                ->values();

            $activeLowStockSub = WarehouseDetail::query()
                ->select([
                    'product_id',
                    DB::raw('SUM(quantity) as total_qty'),
                ])
                ->where('status', 'actived')
                ->groupBy('product_id')
                ->havingRaw('SUM(quantity) < ?', [$lowStockThr]);

            $lowStock = DB::query()
                ->fromSub($activeLowStockSub, 'stock_totals')
                ->join('products', 'products.id', '=', 'stock_totals.product_id')
                ->leftJoin('product_images as pi', 'pi.product_id', '=', 'products.id')
                ->select([
                    DB::raw('stock_totals.product_id as product_id'),
                    'products.name',
                    DB::raw('COALESCE(MIN(pi.url), "") as image_url'),
                    DB::raw('stock_totals.total_qty as total_qty'),
                ])
                ->groupBy('stock_totals.product_id', 'products.name', 'stock_totals.total_qty')
                ->orderByDesc('stock_totals.total_qty')
                ->limit(5)
                ->get()
                ->map(function ($row) {
                    return [
                        'product_id' => (int) $row->product_id,
                        'name'       => (string) ($row->name ?? ''),
                        'image_url'  => (string) $row->image_url,
                        'quantity'   => (int) $row->total_qty,
                    ];
                })
                ->values();

            // fallback: nếu chưa có bản ghi actived, bỏ filter status để vẫn hiện cảnh báo tồn kho
            if ($lowStock->isEmpty()) {
                $allStatusLowStockSub = WarehouseDetail::query()
                    ->select([
                        'product_id',
                        DB::raw('SUM(quantity) as total_qty'),
                    ])
                    ->groupBy('product_id')
                    ->havingRaw('SUM(quantity) < ?', [$lowStockThr]);

                $lowStock = DB::query()
                    ->fromSub($allStatusLowStockSub, 'stock_totals')
                    ->join('products', 'products.id', '=', 'stock_totals.product_id')
                    ->leftJoin('product_images as pi', 'pi.product_id', '=', 'products.id')
                    ->select([
                        DB::raw('stock_totals.product_id as product_id'),
                        'products.name',
                        DB::raw('COALESCE(MIN(pi.url), "") as image_url'),
                        DB::raw('stock_totals.total_qty as total_qty'),
                    ])
                    ->groupBy('stock_totals.product_id', 'products.name', 'stock_totals.total_qty')
                    ->orderByDesc('stock_totals.total_qty')
                    ->limit(5)
                    ->get()
                    ->map(function ($row) {
                        return [
                            'product_id' => (int) $row->product_id,
                            'name'       => (string) ($row->name ?? ''),
                            'image_url'  => (string) $row->image_url,
                            'quantity'   => (int) $row->total_qty,
                        ];
                    })
                    ->values();
            }

            $newCustomers7d = User::query()
                ->where('role', 'user')
                ->whereBetween('created_at', [$topStart, $rangeEnd])
                ->count();

            $revenueTrend = DB::table('order_details')
                ->select([
                    DB::raw('DATE(orders.created_at) as date'),
                    DB::raw('SUM(order_details.quantity * order_details.price) as revenue'),
                    DB::raw('COUNT(DISTINCT orders.id) as orders'),
                ])
                ->join('orders', 'orders.id', '=', 'order_details.order_id')
                ->where('orders.status', 'completed')
                ->whereBetween('orders.created_at', [$rangeStart, $rangeEnd])
                ->groupBy(DB::raw('DATE(orders.created_at)'))
                ->orderBy('date')
                ->get()
                ->keyBy('date');

            $ordersTrend = Order::query()
                ->select([
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('COUNT(*) as total'),
                ])
                ->whereBetween('created_at', [$rangeStart, $rangeEnd])
                ->groupBy(DB::raw('DATE(created_at)'))
                ->orderBy('date')
                ->get()
                ->keyBy('date');

            $purchaseTrend = DB::table('receipt_details')
                ->select([
                    DB::raw('DATE(receipts.created_at) as date'),
                    DB::raw('SUM(receipt_details.quantity) as quantity'),
                    DB::raw('SUM(receipt_details.quantity * receipt_details.purchase_price) as cost'),
                ])
                ->join('receipts', 'receipts.id', '=', 'receipt_details.receipt_id')
                ->where('receipts.status', 'completed')
                ->whereBetween('receipts.created_at', [$rangeStart, $rangeEnd])
                ->groupBy(DB::raw('DATE(receipts.created_at)'))
                ->orderBy('date')
                ->get()
                ->keyBy('date');

            $revenueSeries   = [];
            $ordersSeries    = [];
            $purchasesSeries = [];
            $profitSeries    = [];
            $profitTotal30d  = 0;
            $cursor          = $rangeStart->copy();
            while ($cursor <= $rangeEnd) {
                $dayKey       = $cursor->toDateString();
                $revenue      = (float) ($revenueTrend[$dayKey]->revenue ?? 0);
                $ordersDay    = (int) ($ordersTrend[$dayKey]->total ?? 0);
                $purchaseQty  = (int) ($purchaseTrend[$dayKey]->quantity ?? 0);
                $purchaseCost = (float) ($purchaseTrend[$dayKey]->cost ?? 0);
                $profit       = $revenue - $purchaseCost;

                $revenueSeries[] = [
                    'date'    => $dayKey,
                    'revenue' => round($revenue, 2),
                    'orders'  => $ordersDay,
                ];
                $ordersSeries[] = [
                    'date'   => $dayKey,
                    'orders' => $ordersDay,
                ];
                $purchasesSeries[] = [
                    'date'     => $dayKey,
                    'quantity' => $purchaseQty,
                    'cost'     => round($purchaseCost, 2),
                ];
                $profitSeries[] = [
                    'date'    => $dayKey,
                    'profit'  => round($profit, 2),
                    'revenue' => round($revenue, 2),
                    'cost'    => round($purchaseCost, 2),
                ];
                $profitTotal30d += $profit;
                $cursor->addDay();
            }

            $categoryShare = DB::table('order_details')
                ->select([
                    'categories.name as category',
                    DB::raw('SUM(order_details.quantity * order_details.price) as revenue'),
                ])
                ->join('orders', 'orders.id', '=', 'order_details.order_id')
                ->join('products', 'products.id', '=', 'order_details.product_id')
                ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
                ->where('orders.status', 'completed')
                ->whereBetween('orders.created_at', [$rangeStart, $rangeEnd])
                ->groupBy('categories.name')
                ->orderByDesc('revenue')
                ->get();

            $topCategories = [];
            $otherRevenue  = 0;
            foreach ($categoryShare as $index => $row) {
                if ($index < 4) {
                    $topCategories[] = [
                        'category' => $row->category ?? 'Chưa phân loại',
                        'revenue'  => round((float) $row->revenue, 2),
                    ];
                } else {
                    $otherRevenue += (float) $row->revenue;
                }
            }
            if ($otherRevenue > 0) {
                $topCategories[] = [
                    'category' => 'Khác',
                    'revenue'  => round($otherRevenue, 2),
                ];
            }

            // Đánh giá sản phẩm
            $ratingCounts = Evaluate::query()
                ->whereNotNull('rating')
                ->select(DB::raw('ROUND(rating) as star'), DB::raw('COUNT(*) as total'))
                ->groupBy('star')
                ->pluck('total', 'star')
                ->toArray();
            $ratingTotal = array_sum($ratingCounts);
            $ratingPercent = [];
            for ($i = 1; $i <= 5; $i++) {
                $count = (int) ($ratingCounts[$i] ?? 0);
                $ratingPercent[$i] = $ratingTotal > 0 ? round($count * 100 / $ratingTotal, 2) : 0;
            }
            $ratingAvg = round((float) (Evaluate::query()->whereNotNull('rating')->avg('rating') ?? 0), 2);

            // Thống kê phương thức thanh toán (dùng đúng name trong bảng payments)
            $paymentAgg = DB::table('orders')
                ->select([
                    'orders.payment_id',
                    DB::raw('COUNT(DISTINCT orders.id) as orders'),
                    DB::raw('COALESCE(SUM(order_details.quantity * order_details.price),0) as total_amount'),
                ])
                ->join('order_details', 'order_details.order_id', '=', 'orders.id')
                ->where('orders.status', 'completed')
                ->whereBetween('orders.created_at', [$rangeStart, $rangeEnd])
                ->groupBy('orders.payment_id')
                ->get()
                ->keyBy(fn ($row) => (int) ($row->payment_id ?? 0));

            $payments = Payment::query()->select('id', 'name')->get();
            $paymentStats = collect();
            foreach ($payments as $payment) {
                $agg = $paymentAgg[(int) $payment->id] ?? null;
                $paymentStats->push([
                    'payment_id'   => (int) $payment->id,
                    'name'         => (string) $payment->name,
                    'orders'       => $agg ? (int) $agg->orders : 0,
                    'total_amount' => $agg ? round((float) $agg->total_amount, 2) : 0,
                ]);
            }
            // Đơn không có payment_id
            $unknownAgg = $paymentAgg[0] ?? null;
            if ($unknownAgg) {
                $paymentStats->push([
                    'payment_id'   => 0,
                    'name'         => 'Không xác định',
                    'orders'       => (int) $unknownAgg->orders,
                    'total_amount' => round((float) $unknownAgg->total_amount, 2),
                ]);
            }

            $paymentStats = $paymentStats->sortByDesc('total_amount')->values();
            $paymentTotalAmount = $paymentStats->sum('total_amount');

            $topCustomers = DB::table('orders')
                ->select([
                    'users.id',
                    DB::raw('COALESCE(profiles.name, users.username, "KhÃ¡ch láº»") as customer_name'),
                    DB::raw('SUM(order_details.quantity * order_details.price) as spending'),
                    DB::raw('COUNT(DISTINCT orders.id) as orders'),
                ])
                ->join('order_details', 'order_details.order_id', '=', 'orders.id')
                ->join('users', 'users.id', '=', 'orders.user_id')
                ->leftJoin('profiles', 'profiles.user_id', '=', 'users.id')
                ->whereNotNull('orders.user_id')
                ->where('orders.status', 'completed')
                ->whereBetween('orders.created_at', [$rangeStart, $rangeEnd])
                ->groupBy('users.id', 'profiles.name', 'users.username')
                ->orderByDesc('spending')
                ->limit(5)
                ->get()
                ->map(function ($row) {
                    return [
                        'user_id' => (int) $row->id,
                        'name'    => $row->customer_name ?: 'KhÃ¡ch láº»',
                        'orders'  => (int) $row->orders,
                        'spending'=> round((float) $row->spending, 2),
                    ];
                })
                ->values();

            return response()->json([
                'success' => true,
                'message' => 'Lay thong ke thanh cong',
                'data'    => [
                    'meta' => [
                        'date'  => $date->toDateString(),
                        'range' => [
                            'from' => $rangeStart->toDateString(),
                            'to'   => $date->toDateString(),
                            'days' => $rangeDays,
                        ],
                        'top_days'           => $topDays,
                        'low_stock_threshold'=> $lowStockThr,
                    ],
                    'kpis' => [
                        'revenue_today'         => round((float) $revenueToday, 2),
                        'orders_today'          => $ordersToday,
                        'orders_completed_today'=> count($completedToday),
                        'avg_order_value_today' => count($completedToday) > 0
                            ? round($revenueToday / count($completedToday), 2)
                            : 0,
                        'products_sold_today'   => (int) $productsSoldToday,
                        'receipt_value_today'   => round((float) $receiptValueToday, 2),
                    ],
                    'counters' => [
                        'total_products'        => $totalProducts,
                        'total_users'           => $totalUsers,
                        'new_customers_last_7d' => $newCustomers7d,
                        'low_stock_count'       => $lowStock->count(),
                    ],
                    'metrics' => [
                        'completion_rate'       => $totalOrdersRange > 0
                            ? round(($orderStatusCounts['completed'] ?? 0) * 100 / $totalOrdersRange, 2)
                            : 0,
                        'cancel_reject_rate'    => $totalOrdersRange > 0
                            ? round((($orderStatusCounts['cancelled'] ?? 0) + ($orderStatusCounts['rejected'] ?? 0)) * 100 / $totalOrdersRange, 2)
                            : 0,
                        'avg_order_value_30d'   => count($completedIds30d) > 0
                            ? round($revenueLast30Days / count($completedIds30d), 2)
                            : 0,
                        'inventory_turnover_30d'=> ($purchases30d->total_quantity ?? 0) > 0
                            ? round($quantitySold30d / max(1, (float) $purchases30d->total_quantity), 2)
                            : 0,
                    ],
                    'orders' => [
                        'by_status' => $orderStatusCounts,
                        'trend_30d' => $ordersSeries,
                        'total'     => $totalOrdersRange,
                    ],
                    'revenue' => [
                        'last_7_days'  => round((float) $revenueLast7Days, 2),
                        'last_30_days' => round((float) $revenueLast30Days, 2),
                        'by_day'       => $revenueSeries,
                    ],
                    'purchases' => [
                        'last_7_days_value'  => round((float) ($purchases7d->total_cost ?? 0), 2),
                        'last_30_days_value' => round((float) ($purchases30d->total_cost ?? 0), 2),
                        'last_30_days_qty'   => (int) ($purchases30d->total_quantity ?? 0),
                        'by_day'             => $purchasesSeries,
                    ],
                    'profit' => [
                        'last_30_days' => round((float) $profitTotal30d, 2),
                        'by_day'       => $profitSeries,
                    ],
                    'products' => [
                        'top_selling_7d' => $topProducts7d,
                        'low_stock'      => $lowStock,
                        'category_share' => $topCategories,
                    ],
                    'customers' => [
                        'top_spenders' => $topCustomers,
                    ],
                    'evaluations' => [
                        'counts'   => $ratingCounts,
                        'percents' => $ratingPercent,
                        'average'  => $ratingAvg,
                        'total'    => $ratingTotal,
                    ],
                    'payments' => [
                        'by_method'    => $paymentStats,
                        'total_amount' => round((float) $paymentTotalAmount, 2),
                    ],
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lay thong ke that bai',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
