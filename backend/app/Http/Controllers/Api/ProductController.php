<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $q       = trim((string) $request->query('q', ''));
            $perPage = (int) $request->query('per_page', 10);
            $perPage = $perPage > 0 ? min($perPage, 50) : 10;
            $page    = (int) $request->query('page', 1);

            $cacheKey = 'products:index:' . md5(json_encode([
                'q'        => $q,
                'per_page' => $perPage,
                'page'     => $page,
            ]));

            $payload = Cache::tags(['products'])->remember($cacheKey, 300
                , function () use ($q, $perPage, $page) {
                    $query = Product::query();

                    $query->with(['images', 'category', 'colors']);

                    if ($q !== '') {
                        $query->where('name', 'like', '%' . $q . '%');
                    }

                    $paginator = $query
                        ->orderByDesc('id')
                        ->paginate($perPage, ['*'], 'page', $page);

                    return [
                        'items' => $paginator->items(),
                        'meta'  => [
                            'current_page' => $paginator->currentPage(),
                            'per_page'     => $paginator->perPage(),
                            'total'        => $paginator->total(),
                            'last_page'    => $paginator->lastPage(),
                        ],
                    ];
                });

            return response()->json([
                'success' => true,
                'message' => 'Lấy danh sách sản phẩm thành công',
                'data'    => $payload,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lấy danh sách sản phẩm thất bại. Vui lòng thử lại sau!',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProductRequest $request)
    {
        try {
            $product = null;
            Log::info($request->all());
            DB::transaction(function () use ($request, &$product, &$uploadPublicIds) {
                $product = Product::query()->create($request->all());

                if ($request->has("color_ids")) {
                    $product->colors()->sync($request['color_ids']);
                }

                $images = $request->file('images', []);
                foreach ($images as $image) {
                    $upload = cloudinary()->uploadApi()->upload($image->getRealPath(), [
                        'folder'        => 'products',
                        'resource_type' => 'image',
                    ]);

                    $url      = $upload['secure_url'];
                    $publicId = $upload['public_id'];

                    ProductImage::query()->create([
                        'product_id' => $product->id,
                        'url'        => $url,
                        "public_id"  => $publicId,
                    ]);
                }
            });

            Cache::tags(['products'])->flush();

            return response()->json([
                'success' => true,
                'message' => 'Tạo sản phẩm thành công',
                'data'    => $product,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Tạo sản phẩm thất bại. Vui lòng thử lại sau!',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $cacheKey = "products:show:{$id}";

            $product = Cache::tags(['products'])->remember($cacheKey, 300, function () use ($id) {
                return Product::query()
                    ->find($id)
                    ->load(['images', 'category', 'colors']);
            });
            Log::info($product);
            if (! $product) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy sản phẩm',
                ], 404);
            }

            return response()->json([
                'success'  => true,
                'message'  => 'Lấy chi tiết sản phẩm thành công',
                'products' => $product,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lấy chi tiết sản phẩm thất bại. Vui lòng thử lại sau!',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProductRequest $request, string $productId)
    {
        Log::info($request->all());
        try {
            $product           = Product::query()->find($productId);
            $publicIdsToDelete = []; // public_id cloudinary sẽ xóa sau commit

            DB::transaction(function () use ($request, &$product, &$publicIdsToDelete) {

                // 1) update fields product
                $product->update($request->only([
                    'name', 'des', 'unit', 'category_id',
                ]));

                // 2) sync colors (nếu có key color_ids thì mới sync; gửi [] thì detach hết)
                $colorIds = $request->input('color_ids', []);
                $product->colors()->sync($colorIds);

                // 3) Xóa ảnh theo remove_image_ids[]
                $removeIds = $request->input('remove_image_ids', []);
                if (! empty($removeIds)) {
                    $imagesToRemove = ProductImage::query()
                        ->where('product_id', $product->id)
                        ->whereIn('id', $removeIds)
                        ->get();

                    foreach ($imagesToRemove as $img) {
                        if (! empty($img->public_id)) {
                            $publicIdsToDelete[] = $img->public_id;
                        }
                        $img->delete();
                    }
                }

                // 4) Thay ảnh theo replace_images[image_id]
                // $request->file('replace_images') trả về array keyed theo image_id
                $replaceFiles = $request->file('replace_images', []);
                if (! empty($replaceFiles) && is_array($replaceFiles)) {
                    foreach ($replaceFiles as $imageId => $file) {
                        if (! $file) {
                            continue;
                        }

                        $img = ProductImage::query()
                            ->where('product_id', $product->id)
                            ->where('id', $imageId)
                            ->first();

                        // nếu imageId không thuộc product này thì bỏ qua
                        if (! $img) {
                            continue;
                        }

                        $oldPublicId = $img->public_id;

                        // upload ảnh mới
                        $upload = cloudinary()->uploadApi()->upload($file->getRealPath(), [
                            'folder'        => 'products',
                            'resource_type' => 'image',
                        ]);

                        $img->update([
                            'url'       => $upload['secure_url'] ?? $img->url,
                            'public_id' => $upload['public_id'] ?? $img->public_id,
                        ]);

                        // đánh dấu xóa ảnh cũ sau commit
                        if (! empty($oldPublicId)) {
                            $publicIdsToDelete[] = $oldPublicId;
                        }
                    }
                }

                // 5) Thêm ảnh mới images[]
                $newImages = $request->file('images', []);
                if (! empty($newImages) && is_array($newImages)) {
                    foreach ($newImages as $file) {
                        if (! $file) {
                            continue;
                        }

                        $upload = cloudinary()->uploadApi()->upload($file->getRealPath(), [
                            'folder'        => 'products',
                            'resource_type' => 'image',
                        ]);

                        ProductImage::query()->create([
                            'product_id' => $product->id,
                            'url'        => $upload['secure_url'],
                            'public_id'  => $upload['public_id'],
                        ]);
                    }
                }
            });

            // 6) Sau khi commit: xóa ảnh cũ trên Cloudinary
            $publicIdsToDelete = array_values(array_unique(array_filter($publicIdsToDelete)));
            foreach ($publicIdsToDelete as $pid) {
                try {
                    $res = cloudinary()->uploadApi()->destroy($pid, [
                        'resource_type' => 'image',
                    ]);
                    // ok / not found đều coi như xóa xong
                    $result = $res['result'] ?? null;
                    if (! in_array($result, ['ok', 'not found'], true)) {
                        Log::warning('Cloudinary destroy unexpected result', [
                            'public_id' => $pid,
                            'result'    => $result,
                            'raw'       => $res,
                        ]);
                    }
                } catch (\Throwable $ex) {
                    Log::error('Cloudinary destroy failed', [
                        'public_id' => $pid,
                        'error'     => $ex->getMessage(),
                    ]);
                }
            }

            Cache::tags(['products'])->flush();

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật sản phẩm thành công',
                'data'    => $product->fresh(['colors', 'images']),
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Cập nhật sản phẩm thất bại. Vui phần thử lại sau!',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
