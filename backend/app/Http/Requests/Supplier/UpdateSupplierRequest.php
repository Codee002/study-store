<?php
namespace App\Http\Requests\Supplier;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = (string) $this->route('supplier') ?: (string) $this->route('id');

        return [
            'name' => ['required', 'string', 'min:2', 'max:100', "unique:suppliers,name,{$id}"],
            'address'        => ['required', 'string', 'min:5', 'max:255'],
            'contact_number' => ['required', 'string', 'min:8', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'           => 'Vui lòng nhập tên nhà cung cấp',
            'name.min'                => 'Tên nhà cung cấp tối thiểu 2 ký tự',
            'name.max'                => 'Tên nhà cung cấp tối đa 100 ký tự',
            'name.unique'             => 'Tên nhà cung cấp đã tồn tại',

            'address.required'        => 'Vui lòng nhập địa chỉ nhà cung cấp',
            'address.min'             => 'Địa chỉ tối thiểu 5 ký tự',
            'address.max'             => 'Địa chỉ tối đa 255 ký tự',

            'contact_number.required' => 'Vui lòng nhập số điện thoại liên lạc',
            'contact_number.min'      => 'Số điện thoại tối thiểu 8 ký tự',
            'contact_number.max'      => 'Số điện thoại tối đa 20 ký tự',
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
