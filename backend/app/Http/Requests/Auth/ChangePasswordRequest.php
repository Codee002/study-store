<?php
namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rules\Password;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_password'          => ['required', 'string'],
            'password'                  => ['required', 'confirmed', Password::min(6)],
            'password_confirmation'     => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required'      => 'Vui lòng nhập mật khẩu hiện tại',
            'password.required'              => 'Vui lòng nhập mật khẩu mới',
            'password.confirmed'             => 'Mật khẩu xác nhận không khớp',
            'password.min'                   => 'Mật khẩu mới tối thiểu 6 ký tự',
            'password_confirmation.required' => 'Vui lòng nhập lại mật khẩu mới',
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
