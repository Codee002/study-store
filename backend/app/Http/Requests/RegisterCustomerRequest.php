<?php
namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email'    => [
                'required',
                'email:rfc,dns',
                'max:255',
                Rule::unique('users', 'email'),
            ],
            'phone'    => [
                'required',
                'string',
                'max:20',
                'regex:/^(0|\+84)(3|5|7|8|9)\d{8}$/',
                Rule::unique('profiles', 'phone'),
            ],
            'username' => [
                'required',
                'string',
                'min:5',
                'max:30',
                'regex:/^[A-Za-z][A-Za-z0-9]*$/',
                Rule::unique('users', 'username'),
            ],
            'password' => [
                'required',
                'confirmed',
                Password::min(6),
            ],
            'birthday' => [
                'required',
                'date',
                'before:today',
            ],
            'gender'   => [
                'required',
                Rule::in(['male', 'female']),
            ],
            'agree'    => [
                'required',
                'accepted',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'required'           => ':attribute không được để trống.',
            'email.email'        => ':attribute không hợp lệ.',
            'email.unique'       => ':attribute đã được sử dụng.',
            'email.max'          => ':attribute tối đa :max ký tự.',

            'phone.regex'        => ':attribute không hợp lệ.',
            'phone.unique'       => ':attribute đã được sử dụng.',
            'phone.max'          => ':attribute tối đa :max ký tự.',

            'username.min'       => ':attribute tối thiểu :min ký tự.',
            'username.max'       => ':attribute tối đa :max ký tự.',
            'username.regex'     => ':attribute phải bắt đầu bằng chữ và chỉ gồm chữ và số.',
            'username.unique'    => ':attribute đã được sử dụng.',

            'password.confirmed' => 'Nhập lại mật khẩu không khớp.',
            'password.min'       => ':attribute tối thiểu :min ký tự.',

            'birthday.date'      => ':attribute không hợp lệ.',
            'birthday.before'    => ':attribute không được lớn hơn hôm nay.',

            'gender.in'          => ':attribute không hợp lệ.',
            'agree.accepted'     => 'Bạn cần đồng ý điều khoản để tiếp tục.',
        ];
    }

    public function attributes(): array
    {
        return [
            'email'                 => 'Email',
            'phone'                 => 'Số điện thoại',
            'username'              => 'Tên đăng nhập',
            'password'              => 'Mật khẩu',
            'password_confirmation' => 'Nhập lại mật khẩu',
            'birthday'              => 'Ngày sinh',
            'gender'                => 'Giới tính',
            'agree'                 => 'Điều khoản',
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
