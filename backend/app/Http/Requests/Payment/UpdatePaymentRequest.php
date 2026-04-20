<?php
namespace App\Http\Requests\Payment;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdatePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = (string) $this->route('payment') ?: (string) $this->route('id');

        return [
            'name' => ['required', 'string', 'min:2', 'max:100', "unique:payments,name,{$id}"],
            'status' => ['required', Rule::in(['actived', 'disabled'])],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Vui lòng chọn tên phương thức thanh toán',
            'name.min' => 'Tên phương thức thanh toán tối thiểu 2 ký tự',
            'name.max' => 'Tên phương thức thanh toán tối đa 100 ký tự',
            'name.unique' => 'Tên phương thức thanh toán đã tồn tại',
            'status.required' => 'Vui lòng chọn trạng thái',
            'status.in' => 'Trạng thái không hợp lệ',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Dữ liệu không hợp lệ',
            'errors' => $validator->errors(),
        ], 422));
    }
}
