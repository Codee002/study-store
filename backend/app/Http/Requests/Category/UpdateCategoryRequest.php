<?php
namespace App\Http\Requests\Category;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // $id = $this->route('id');
        $id = (string) $this->route('category') ?: (string) $this->route('id');
        return [
            'name' => [
                'required',
                'string',
                'min:2',
                'max:100',
                "unique:categories,name,{$id}",
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Vui lòng nhập tên danh mục',
            'name.min'      => 'Tên danh mục tối thiểu 2 ký tự',
            'name.max'      => 'Tên danh mục tối đa 100 ký tự',
            'name.unique'   => 'Tên danh mục đã tồn tại',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            "success" => false,
            'message' => 'Lỗi xác thực dữ liệu',
            'errors'  => $validator->errors(),
        ], 422));
    }
}
