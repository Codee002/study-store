<?php
namespace App\Http\Requests\Product;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        Log::info(request()->all());

        return [
            'name'               => 'required|string|max:150',
            'des'                => 'required|string|max:2000',
            'unit'               => 'required|string|max:30',
            'category_id'        => 'required|exists:categories,id',

            // update: images không bắt buộc
            'images'             => 'nullable|array',
            'images.*'           => 'nullable|image',

            // colors
            'color_ids'          => ['nullable', 'array'],
            'color_ids.*'        => ['nullable', 'exists:colors,id'],

            // xóa ảnh theo id trong bảng product_images
            'remove_image_ids'   => ['nullable', 'array'],
            'remove_image_ids.*' => ['integer', 'exists:product_images,id'],

            // thay ảnh: replace_images[image_id] = file
            'replace_images'     => ['nullable', 'array'],
            'replace_images.*'   => ['nullable', 'image'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'             => 'Tên sản phẩm không được để trống',
            'des.required'              => 'Mô tả sản phẩm không được để trống',
            'unit.required'             => 'Đơn vị tính không được để trống',
            'category_id.required'      => 'Danh mục sản phẩm không được để trống',
            'category_id.exists'        => 'Danh mục sản phẩm không tồn tại',

            'images.array'              => 'Danh sách hình ảnh không hợp lệ',
            'images.*.image'            => 'Định dạng phải là ảnh',

            'color_ids.array'           => 'Mảng màu sắc không hợp lệ',
            'color_ids.*.integer'       => 'ID màu sắc phải là số nguyên',

            'remove_image_ids.array'    => 'Danh sách ảnh xóa không hợp lệ',
            'remove_image_ids.*.exists' => 'Ảnh cần xóa không tồn tại',

            'replace_images.array'      => 'Danh sách ảnh thay thế không hợp lệ',
            'replace_images.*.image'    => 'Ảnh thay thế phải là định dạng ảnh',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Dữ liệu không hợp lệ',
            'errors'  => $validator->errors(),
        ], 422));
    }
}
