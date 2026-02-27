<?php
namespace App\Http\Requests\Payment;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100', 'unique:payments,name'],
            'status' => ['required', Rule::in(['actived', 'disabled'])],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Vui long nhap ten phuong thuc thanh toan',
            'name.min' => 'Ten phuong thuc thanh toan toi thieu 2 ky tu',
            'name.max' => 'Ten phuong thuc thanh toan toi da 100 ky tu',
            'name.unique' => 'Ten phuong thuc thanh toan da ton tai',
            'status.required' => 'Vui long chon trang thai',
            'status.in' => 'Trang thai khong hop le',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Du lieu khong hop le',
            'errors' => $validator->errors(),
        ], 422));
    }
}
