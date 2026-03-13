<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\Receipt;
use App\Models\ReceiptDetail;
use App\Models\WarehouseDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ProductStatsController extends Controller
{
    private function ensureAdmin(Request $request): void
    {
        $user = $request->user();
        if (! $user || (string) ($user->role ?? '') !== 'admin') {
            abort(403, 'Khong co quyen truy cap');
        }
    }

    public function index(Request $request)
    {
        $this->ensureAdmin($request);

        $q       = trim((string) $request->query('q', ''));
        $perPage = (int) $request->query('per_page', 10);
        $perPage = $perPage > 0 ? min($perPage, 100) : 10;
        $page    = (int) $request->query('page', 1);

        $query = $this->baseQuery($q);

        $paginator = $query
            ->orderBy('products.name')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
            'message' => 'Lay thong ke san pham thanh cong',
            'data'    => [
                'items' => $paginator->items(),
                'meta'  => [
                    'current_page' => $paginator->currentPage(),
                    'per_page'     => $paginator->perPage(),
                    'total'        => $paginator->total(),
                    'last_page'    => $paginator->lastPage(),
                ],
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->ensureAdmin($request);

        $q = trim((string) $request->query('q', ''));

        $rows = $this->baseQuery($q)
            ->orderBy('products.name')
            ->get();

        $filename = 'product-stats-' . now()->format('Ymd_His') . '.xlsx';

        $headers = [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($rows) {
            $sheet = new Spreadsheet();
            $sheet->getProperties()->setTitle('Product Stats');
            $active = $sheet->getActiveSheet();
            $active->setTitle('Stats');

            $headers = ['Product ID', 'Name', 'Category', 'Avg Purchase', 'Avg Selling', 'Purchased Qty', 'Sold Qty', 'Stock'];
            $active->fromArray($headers, null, 'A1');

            $rowIndex = 2;
            foreach ($rows as $row) {
                $active->fromArray([
                    $row->id,
                    $row->name,
                    $row->category_name ?? '',
                    round((float) $row->avg_purchase_price, 2),
                    round((float) $row->avg_selling_price, 2),
                    (int) $row->purchased_qty,
                    (int) $row->sold_qty,
                    (int) $row->stock_qty,
                ], null, 'A' . $rowIndex);
                $rowIndex++;
            }

            // Basic column autosize for readability
            foreach (range('A', 'H') as $col) {
                $active->getColumnDimension($col)->setAutoSize(true);
            }

            $writer = new Xlsx($sheet);
            // Stream directly to the output buffer
            $writer->save('php://output');
        };

        return response()->stream($callback, 200, $headers);
    }

    private function baseQuery(string $q)
    {
        $purchaseSub = ReceiptDetail::query()
            ->selectRaw(
                'CASE WHEN SUM(receipt_details.quantity)=0 THEN 0 ELSE SUM(receipt_details.quantity * receipt_details.purchase_price) / SUM(receipt_details.quantity) END'
            )
            ->join('receipts', 'receipts.id', '=', 'receipt_details.receipt_id')
            ->where('receipts.status', 'completed')
            ->whereColumn('receipt_details.product_id', 'products.id');

        $sellingSub = OrderDetail::query()
            ->selectRaw(
                'CASE WHEN SUM(order_details.quantity)=0 THEN 0 ELSE SUM(order_details.quantity * order_details.price) / SUM(order_details.quantity) END'
            )
            ->join('orders', 'orders.id', '=', 'order_details.order_id')
            ->where('orders.status', 'completed')
            ->whereColumn('order_details.product_id', 'products.id');

        $purchasedQtySub = ReceiptDetail::query()
            ->selectRaw('COALESCE(SUM(receipt_details.quantity),0)')
            ->join('receipts', 'receipts.id', '=', 'receipt_details.receipt_id')
            ->where('receipts.status', 'completed')
            ->whereColumn('receipt_details.product_id', 'products.id');

        $soldQtySub = OrderDetail::query()
            ->selectRaw('COALESCE(SUM(order_details.quantity),0)')
            ->join('orders', 'orders.id', '=', 'order_details.order_id')
            ->where('orders.status', 'completed')
            ->whereColumn('order_details.product_id', 'products.id');

        $stockSub = WarehouseDetail::query()
            ->selectRaw('COALESCE(SUM(quantity),0)')
            ->where('status', 'actived')
            ->whereColumn('product_id', 'products.id');

        $firstImageSub = DB::table('product_images')
            ->select('url')
            ->whereColumn('product_images.product_id', 'products.id')
            ->orderBy('id')
            ->limit(1);

        $query = Product::query()
            ->select([
                'products.id',
                'products.name',
                'categories.name as category_name',
            ])
            ->selectSub($purchaseSub, 'avg_purchase_price')
            ->selectSub($sellingSub, 'avg_selling_price')
            ->selectSub($purchasedQtySub, 'purchased_qty')
            ->selectSub($soldQtySub, 'sold_qty')
            ->selectSub($stockSub, 'stock_qty')
            ->selectSub($firstImageSub, 'image_url')
            ->join('categories', 'categories.id', '=', 'products.category_id');

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('products.name', 'like', "%{$q}%")
                    ->orWhere('products.code', 'like', "%{$q}%");
            });
        }

        return $query;
    }
}
