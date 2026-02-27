<?php
namespace App\Http\Requests\Profile;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $profileId = (int) optional($this->user())->profile?->id;

        return [
            'name'     => ['required', 'string', 'min:2', 'max:100'],
            'phone'    => [
                'required',
                'string',
                'max:20',
                'regex:/^(0|\+84)(3|5|7|8|9)\d{8}$/',
                Rule::unique('profiles', 'phone')->ignore($profileId),
            ],
            'birthday' => ['nullable', 'date', 'before:today'],
            'gender'   => ['nullable', Rule::in(['male', 'female'])],
            'avatar'   => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'     => 'Vui lòng nhập họ và tên',
            'name.min'          => 'Họ và tên tối thiểu 2 ký tự',
            'name.max'          => 'Họ và tên tối đa 100 ký tự',

            'phone.required'    => 'Vui lòng nhập số điện thoại',
            'phone.regex'       => 'Số điện thoại không đúng định dạng Việt Nam',
            'phone.max'         => 'Số điện thoại tối đa 20 ký tự',
            'phone.unique'      => 'Số điện thoại đã được sử dụng',

            'birthday.date'     => 'Ngày sinh không hợp lệ',
            'birthday.before'   => 'Ngày sinh phải trước ngày hiện tại',

            'gender.in'         => 'Giới tính không hợp lệ',

            'avatar.image'      => 'Ảnh đại diện phải là tệp ảnh',
            'avatar.mimes'      => 'Ảnh đại diện chỉ hỗ trợ jpg, jpeg, png, webp',
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
