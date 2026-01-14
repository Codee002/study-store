<?php
namespace App\Http\Requests\Warehouse;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdateWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = (string) $this->route('warehouse') ?: (string) $this->route('id');

        return [
            'address'  => [
                'required',
                'string',
                'max:255',
                Rule::unique('warehouses', 'address')->ignore($id),
            ],
            'capacity' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'address.required'  => 'Vui lòng nhập địa chỉ kho',
            'address.max'       => 'Địa chỉ kho tối đa 255 ký tự',
            'address.unique'    => 'Địa chỉ kho đã tồn tại',

            'capacity.required' => 'Vui lòng nhập dung tích kho',
            'capacity.integer'  => 'Dung tích kho phải là số nguyên',
            'capacity.min'      => 'Dung tích kho phải lớn hơn 0',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Lỗi xác thực dữ liệu',
            'errors'  => $validator->errors(),
        ], 422));
    }
}
