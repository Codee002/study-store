<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\Tier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class PriceQuotationController extends Controller
{
    public function adminExport(Request $request)
    {
        try {
            $user = $request->user();
            if (! $user || (string) $user->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Ban khong co quyen thuc hien chuc nang nay',
                ], 403);
            }

            $tierId = (int) $request->query('tier_id', 0);
            if ($tierId <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Thieu tier_id hop le',
                ], 422);
            }

            $tier = Tier::query()->find($tierId);
            if (! $tier) {
                return response()->json([
                    'success' => false,
                    'message' => 'Khong tim thay tier',
                ], 404);
            }

            return $this->streamSpreadsheetForTier($tier, 'admin');
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Xuat bao gia that bai',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function myExport(Request $request)
    {
        try {
            $user = $request->user();
            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Chua dang nhap',
                ], 401);
            }

            $user->loadMissing(['dealerProfile', 'tier']);
            $tierId = (int) ($user->tier_id ?: ($user->dealerProfile?->tier_id ?: 0));

            if ($tierId <= 0) {
                $tierId = (int) (Tier::query()->where('code', 'RETAIL')->value('id')
                    ?: Tier::query()->where('default', 1)->value('id'));
            }

            if ($tierId <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Khong xac dinh duoc tier hien tai cua nguoi dung',
                ], 422);
            }

            $tier = Tier::query()->find($tierId);
            if (! $tier) {
                return response()->json([
                    'success' => false,
                    'message' => 'Khong tim thay tier',
                ], 404);
            }

            return $this->streamSpreadsheetForTier($tier, 'customer');
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Xuat bao gia that bai',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function validatePurchaseFile(Request $request)
    {
        try {
            $user = $request->user();
            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Chua dang nhap',
                ], 401);
            }

            $validated = $request->validate([
                'rows' => ['required', 'array', 'min:1'],
                'rows.*.row_no' => ['nullable', 'integer', 'min:1'],
                'rows.*.product_id' => ['nullable', 'integer', 'min:1'],
                'rows.*.product_name' => ['nullable', 'string', 'max:255'],
                'rows.*.color_option' => ['nullable', 'string', 'max:255'],
                'rows.*.unit' => ['nullable', 'string', 'max:100'],
                'rows.*.min_quantity' => ['nullable'],
                'rows.*.quantity' => ['nullable'],
            ]);

            $tierId = $this->resolveEffectiveTierId($user);
            if ($tierId <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Khong xac dinh duoc tier hien tai cua nguoi dung',
                ], 422);
            }

            $rows = collect($validated['rows'] ?? []);
            $productIds = $rows->pluck('product_id')
                ->filter(fn ($id) => (int) $id > 0)
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            $productNames = $rows->pluck('product_name')
                ->map(fn ($name) => trim((string) $name))
                ->filter()
                ->unique()
                ->values();

            $products = collect();
            if ($productIds->isNotEmpty() || $productNames->isNotEmpty()) {
                $products = Product::query()
                    ->with([
                        'colors:id,color_name',
                        'prices' => function ($q) use ($tierId) {
                            $q->where('tier_id', $tierId)
                                ->with('tier:id,code,name')
                                ->orderBy('min_quantity')
                                ->orderBy('id');
                        },
                    ])
                    ->where(function ($query) use ($productIds, $productNames) {
                        if ($productIds->isNotEmpty()) {
                            $query->whereIn('id', $productIds->all());
                        }

                        foreach ($productNames as $name) {
                            $query->orWhereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($name)]);
                        }
                    })
                    ->get();
            }

            $productsById = $products->keyBy(fn ($product) => (int) $product->id);
            $productsByName = collect();
            foreach ($products as $product) {
                $name = trim((string) ($product->name ?? ''));
                $productsByName->put($this->normalizeLookupText($name), $product);
                $productsByName->put($this->normalizeLookupTextInsensitive($name), $product);
            }
            $stockMap = $this->getAvailableStockMap($products->pluck('id')->map(fn ($id) => (int) $id)->all());

            $resultRows = [];
            $validCount = 0;
            $invalidCount = 0;

            foreach ($rows as $row) {
                $validatedRow = $this->validatePurchaseRow(
                    is_array($row) ? $row : [],
                    $productsById,
                    $productsByName,
                    $stockMap,
                    $tierId
                );

                if ($validatedRow['is_valid']) {
                    $validCount++;
                } else {
                    $invalidCount++;
                }

                $resultRows[] = $validatedRow;
            }

            return response()->json([
                'success' => true,
                'message' => 'Kiem tra file mua hang thanh cong',
                'data' => [
                    'rows' => $resultRows,
                    'summary' => [
                        'total_rows' => count($resultRows),
                        'valid_rows' => $validCount,
                        'invalid_rows' => $invalidCount,
                    ],
                ],
            ], 200);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Kiem tra file mua hang that bai',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function streamSpreadsheetForTier(Tier $tier, string $scope)
    {
        $tierId = (int) $tier->id;
        $rows = $this->collectQuotationRows($tierId);

        $timestamp = now()->format('Ymd_His');
        $filename = sprintf('bao-gia-%s-%s.xlsx', $scope, $timestamp);

        return response()->streamDownload(function () use ($rows) {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            $headers = [
                'A1' => 'product_id',
                'B1' => 'product_name',
                'C1' => 'product_category',
                'D1' => 'color_options',
                'E1' => 'unit',
                'F1' => 'min_quantity',
                'G1' => 'price',
                'H1' => 'exported_at',
            ];
            foreach ($headers as $cell => $text) {
                $sheet->setCellValue($cell, $text);
            }

            $exportedAt = now()->format('Y-m-d H:i:s');
            $rowIdx = 2;
            foreach ($rows as $row) {
                $sheet->setCellValue("A{$rowIdx}", (int) $row['product_id']);
                $sheet->setCellValue("B{$rowIdx}", (string) $row['product_name']);
                $sheet->setCellValue("C{$rowIdx}", (string) $row['product_category']);
                $sheet->setCellValue("D{$rowIdx}", (string) $row['color_options']);
                $sheet->setCellValue("E{$rowIdx}", (string) $row['unit']);
                $sheet->setCellValue("F{$rowIdx}", (int) $row['min_quantity']);
                $sheet->setCellValue("G{$rowIdx}", (float) $row['price']);
                $sheet->setCellValue("H{$rowIdx}", $exportedAt);
                $rowIdx++;
            }

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function collectQuotationRows(int $tierId): array
    {
        $products = Product::query()
            ->with([
                'category:id,name',
                'colors:id,color_name',
                'prices' => function ($q) use ($tierId) {
                    $q->where('tier_id', $tierId)
                        ->orderBy('min_quantity')
                        ->orderBy('id');
                },
            ])
            ->whereHas('prices', function ($q) use ($tierId) {
                $q->where('tier_id', $tierId);
            })
            ->orderBy('category_id')
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        $rows = [];
        foreach ($products as $product) {
            $colorNames = collect($product->colors ?? [])
                ->map(fn ($c) => trim((string) ($c->color_name ?? '')))
                ->filter()
                ->unique()
                ->values()
                ->all();

            $colorOptions = implode(' | ', $colorNames);

            foreach ($product->prices as $priceRow) {
                $rows[] = [
                    'product_id' => (int) ($product->id ?? 0),
                    'product_name' => (string) ($product->name ?? ''),
                    'product_category' => (string) ($product->category?->name ?? ''),
                    'color_options' => $colorOptions,
                    'unit' => (string) ($product->unit ?? ''),
                    'min_quantity' => (int) ($priceRow->min_quantity ?? 1),
                    'price' => round((float) ($priceRow->price ?? 0), 2),
                ];
            }
        }

        return $rows;
    }

    private function validatePurchaseRow(array $row, $productsById, $productsByName, array $stockMap, int $tierId): array
    {
        $rowNo = max(1, (int) ($row['row_no'] ?? 1));
        $inputProductId = (int) ($row['product_id'] ?? 0);
        $inputProductName = trim((string) ($row['product_name'] ?? ''));
        $inputColor = trim((string) ($row['color_option'] ?? ''));
        $inputUnit = trim((string) ($row['unit'] ?? ''));
        $quantityValue = $row['quantity'] ?? $row['min_quantity'] ?? null;
        $quantity = is_numeric($quantityValue) ? (int) $quantityValue : 0;

        $errors = [];
        $warnings = [];
        $matchedProduct = null;

        if ($inputProductId > 0) {
            $matchedProduct = $productsById->get($inputProductId);
            if (! $matchedProduct) {
                $errors[] = 'Không tìm thấy sản phẩm theo product_id';
            }
        } elseif ($inputProductName !== '') {
            $matchedProduct = $productsByName->get($this->normalizeLookupText($inputProductName))
                ?? $productsByName->get($this->normalizeLookupTextInsensitive($inputProductName));
            if (! $matchedProduct) {
                $errors[] = 'Không tìm thấy sản phẩm theo product_name';
            }
        } else {
            $errors[] = 'Thiếu product_id hoặc product_name';
        }

        if ($quantity <= 0) {
            $errors[] = 'Số lượng đặt mua phải lớn hơn 0';
        }

        $matchedColor = null;
        $availableStock = 0;
        $unitPrice = 0.0;
        $lineTotal = 0.0;
        $appliedMinQuantity = null;

        if ($matchedProduct) {
            if (
                $inputProductName !== ''
                && $this->normalizeLookupText($inputProductName) !== $this->normalizeLookupText((string) ($matchedProduct->name ?? ''))
                && $this->normalizeLookupTextInsensitive($inputProductName) !== $this->normalizeLookupTextInsensitive((string) ($matchedProduct->name ?? ''))
            ) {
                $warnings[] = 'product_name không khớp với dữ liệu sản phẩm, hệ thống ưu tiên product_id';
            }

            $productUnit = trim((string) ($matchedProduct->unit ?? ''));
            if ($inputUnit !== '' && mb_strtolower($inputUnit) !== mb_strtolower($productUnit)) {
                $errors[] = 'Unit không khớp với sản phẩm';
            }

            $colorOptions = collect($matchedProduct->colors ?? []);
            if ($colorOptions->isNotEmpty()) {
                if ($inputColor === '') {
                    $errors[] = 'Sản phẩm yêu cầu color_option';
                } else {
                    $matchedColor = $colorOptions->first(function ($color) use ($inputColor) {
                        $colorName = trim((string) ($color->color_name ?? ''));
                        return $this->normalizeLookupText($colorName) === $this->normalizeLookupText($inputColor)
                            || $this->normalizeLookupTextInsensitive($colorName) === $this->normalizeLookupTextInsensitive($inputColor);
                    });

                    if (! $matchedColor) {
                        $errors[] = 'Màu sắc không tồn tại với sản phẩm';
                    }
                }
            } elseif ($inputColor !== '' && ! in_array($this->normalizeLookupText($inputColor), ['mac dinh', 'default'], true)) {
                $warnings[] = 'Sản phẩm này không có màu';
            }

            $stockKey = $this->stockMapKey(
                (int) $matchedProduct->id,
                $matchedColor ? (int) $matchedColor->id : null
            );
            $availableStock = (int) ($stockMap[$stockKey] ?? 0);

            if ($quantity > 0 && $availableStock <= 0) {
                $errors[] = 'Sản phẩm hoặc màu đang hết hàng';
            } elseif ($quantity > $availableStock) {
                $errors[] = 'Số lượng vượt quá tồn kho hiện tại';
            }

            $pricing = $this->resolveUnitPriceForTier($matchedProduct->prices ?? [], $tierId, $quantity);
            if ($quantity > 0 && ! $pricing) {
                $errors[] = 'Sản phẩm chưa có bảng giá cho tier hiện tại';
            } elseif ($pricing) {
                $unitPrice = (float) ($pricing['unit_price'] ?? 0);
                $appliedMinQuantity = (int) ($pricing['min_quantity'] ?? 1);
                $lineTotal = round($unitPrice * $quantity, 2);
            }
        }

        return [
            'row_no' => $rowNo,
            'product_id' => $inputProductId > 0 ? $inputProductId : ($matchedProduct ? (int) $matchedProduct->id : null),
            'product_name' => $inputProductName !== '' ? $inputProductName : (string) ($matchedProduct->name ?? ''),
            'color_option' => $inputColor !== '' ? $inputColor : (string) ($matchedColor->color_name ?? ''),
            'unit' => $inputUnit !== '' ? $inputUnit : (string) ($matchedProduct->unit ?? ''),
            'min_quantity' => $quantity,
            'quantity' => $quantity,
            'matched_product_id' => $matchedProduct ? (int) $matchedProduct->id : null,
            'matched_color_id' => $matchedColor ? (int) $matchedColor->id : null,
            'available_stock' => $availableStock,
            'unit_price' => $unitPrice,
            'line_total' => $lineTotal,
            'applied_min_quantity' => $appliedMinQuantity,
            'is_valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    private function getAvailableStockMap(array $productIds): array
    {
        $productIds = array_values(array_unique(array_filter(array_map(fn ($id) => (int) $id, $productIds))));
        if (empty($productIds)) {
            return [];
        }

        $warehouseRows = DB::table('warehouse_details')
            ->select('product_id', 'color_id', DB::raw('SUM(quantity) as stock_quantity'))
            ->whereIn('product_id', $productIds)
            ->where('status', 'actived')
            ->groupBy('product_id', 'color_id')
            ->get();

        $reservedRows = OrderDetail::query()
            ->join('orders', 'orders.id', '=', 'order_details.order_id')
            ->select('order_details.product_id', 'order_details.color_id', DB::raw('SUM(order_details.quantity) as reserved_quantity'))
            ->whereIn('order_details.product_id', $productIds)
            ->where('orders.status', 'pending')
            ->groupBy('order_details.product_id', 'order_details.color_id')
            ->get();

        $warehouseMap = [];
        foreach ($warehouseRows as $row) {
            $warehouseMap[$this->stockMapKey((int) $row->product_id, $row->color_id === null ? null : (int) $row->color_id)] = (int) ($row->stock_quantity ?? 0);
        }

        $reservedMap = [];
        foreach ($reservedRows as $row) {
            $reservedMap[$this->stockMapKey((int) $row->product_id, $row->color_id === null ? null : (int) $row->color_id)] = (int) ($row->reserved_quantity ?? 0);
        }

        $stockMap = [];
        foreach ($warehouseMap as $key => $quantity) {
            $stockMap[$key] = max(0, $quantity - (int) ($reservedMap[$key] ?? 0));
        }

        return $stockMap;
    }

    private function stockMapKey(int $productId, ?int $colorId): string
    {
        return $productId . '-' . ($colorId === null ? 'null' : $colorId);
    }

    private function normalizeLookupText(string $value): string
    {
        return mb_strtolower(trim($value));
    }

    private function normalizeLookupTextInsensitive(string $value): string
    {
        $lower = mb_strtolower(trim($value));
        $replacements = [
            'à' => 'a', 'á' => 'a', 'ạ' => 'a', 'ả' => 'a', 'ã' => 'a',
            'ă' => 'a', 'ằ' => 'a', 'ắ' => 'a', 'ặ' => 'a', 'ẳ' => 'a', 'ẵ' => 'a',
            'â' => 'a', 'ầ' => 'a', 'ấ' => 'a', 'ậ' => 'a', 'ẩ' => 'a', 'ẫ' => 'a',
            'è' => 'e', 'é' => 'e', 'ẹ' => 'e', 'ẻ' => 'e', 'ẽ' => 'e',
            'ê' => 'e', 'ề' => 'e', 'ế' => 'e', 'ệ' => 'e', 'ể' => 'e', 'ễ' => 'e',
            'ì' => 'i', 'í' => 'i', 'ị' => 'i', 'ỉ' => 'i', 'ĩ' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ọ' => 'o', 'ỏ' => 'o', 'õ' => 'o',
            'ô' => 'o', 'ồ' => 'o', 'ố' => 'o', 'ộ' => 'o', 'ổ' => 'o', 'ỗ' => 'o',
            'ơ' => 'o', 'ờ' => 'o', 'ớ' => 'o', 'ợ' => 'o', 'ở' => 'o', 'ỡ' => 'o',
            'ù' => 'u', 'ú' => 'u', 'ụ' => 'u', 'ủ' => 'u', 'ũ' => 'u',
            'ư' => 'u', 'ừ' => 'u', 'ứ' => 'u', 'ự' => 'u', 'ử' => 'u', 'ữ' => 'u',
            'ỳ' => 'y', 'ý' => 'y', 'ỵ' => 'y', 'ỷ' => 'y', 'ỹ' => 'y',
            'đ' => 'd',
        ];

        return strtr($lower, $replacements);
    }

    private function resolveUnitPriceForTier($prices, int $tierId, int $quantity): ?array
    {
        $rows = collect($prices)
            ->filter(fn ($row) => (int) ($row->tier_id ?? 0) === $tierId)
            ->sortBy('min_quantity')
            ->values();

        if ($rows->isEmpty()) {
            return null;
        }

        $applied = $rows->first();
        foreach ($rows as $row) {
            if ((int) ($row->min_quantity ?? 0) <= $quantity) {
                $applied = $row;
            }
        }

        return [
            'unit_price' => (float) ($applied->price ?? 0),
            'min_quantity' => (int) ($applied->min_quantity ?? 1),
        ];
    }

    private function resolveEffectiveTierId($user): int
    {
        if (! $user) {
            return 0;
        }

        $user->loadMissing(['dealerProfile', 'tier']);
        $tierId = (int) ($user->tier_id ?: ($user->dealerProfile?->tier_id ?: 0));

        if ($tierId <= 0) {
            $tierId = (int) (Tier::query()->where('code', 'RETAIL')->value('id')
                ?: Tier::query()->where('default', 1)->value('id'));
        }

        return max(0, $tierId);
    }
}
