<?php
namespace App\Http\Requests\Product;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name'        => 'required|string|max:150',
            'des'         => 'nullable|string|max:2000',
            'unit'        => 'required|string|max:30',
            'category_id' => 'required|exists:categories,id',
            'images'      => 'required|array|min:1',
            'images.*'    => 'required|image',
            'color_ids'   => ['nullable', 'array'],
            'color_ids.*' => ['nullable', 'exists:colors,id'],

        ];
    }

    public function messages(): array
    {
        return [
            'name.required'        => 'Tên sản phẩm không được để trống',
            'name.string'          => 'Tên sản phẩm phải là chuỗi ký tự',
            'name.max'             => 'Tên sản phẩm không được vượt quá 150 ký tự',
            'des.string'           => 'Mô tả sản phẩm phải là chuỗi ký tự',
            'des.max'              => 'Mô tả sản phẩm không được vượt quá 2000 ký tự',
            'unit.required'        => 'Đơn vị tính không được để trống',
            'unit.string'          => 'Đơn vị tính phải là chuỗi ký tự',
            'unit.max'             => 'Đơn vị tính không được vượt quá 30 ký tự',
            'category_id.required' => 'Danh mục sản phẩm không được để trống',
            'category_id.exists'   => 'Danh mục sản phẩm không tồn tại',
            'images.required'      => 'Hình ảnh sản phẩm không được để trống',
            'images.min'           => 'Cần ít nhất một hình ảnh cho sản phẩm',
            'images.*.image'       => 'Định dạng phải là ảnh',
            'color_ids.array'      => 'Mảng màu sắc không hợp lệ',
            'color_ids.*.integer'  => 'ID màu sắc phải là số nguyên',
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
