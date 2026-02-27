<?php
namespace App\Http\Requests\DeliveryInfo;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreDeliveryInfoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'    => ['required', 'string', 'min:2', 'max:100'],
            'phone'   => ['required', 'string', 'max:20', 'regex:/^(0|\+84)(3|5|7|8|9)\d{8}$/'],
            'address' => ['required', 'string', 'min:5', 'max:500'],
            'default' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'    => 'Vui lòng nhập tên người nhận',
            'name.min'         => 'Tên người nhận tối thiểu 2 ký tự',
            'name.max'         => 'Tên người nhận tối đa 100 ký tự',

            'phone.required'   => 'Vui lòng nhập số điện thoại',
            'phone.max'        => 'Số điện thoại tối đa 20 ký tự',
            'phone.regex'      => 'Số điện thoại không đúng định dạng Việt Nam',

            'address.required' => 'Vui lòng nhập địa chỉ giao hàng',
            'address.min'      => 'Địa chỉ giao hàng tối thiểu 5 ký tự',
            'address.max'      => 'Địa chỉ giao hàng tối đa 500 ký tự',
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
