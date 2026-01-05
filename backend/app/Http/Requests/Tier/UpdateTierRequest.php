<?php
namespace App\Http\Requests\Tier;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdateTierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = (string) $this->route('tier') ?: (string) $this->route('id');

        return [
            'name' => ['required', 'string', 'min:2', 'max:100', "unique:tiers,name,{$id}"],
            'code' => ['required', 'string', 'min:2', 'max:50', "unique:tiers,code,{$id}"],
            'status'     => ['required', Rule::in(['actived', 'disabled'])],
            'is_default' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'      => 'Vui lòng nhập tên cấp tài khoản',
            'name.min'           => 'Tên tối thiểu 2 ký tự',
            'name.max'           => 'Tên tối đa 100 ký tự',
            'name.unique'        => 'Tên cấp tài khoản đã tồn tại',

            'code.required'      => 'Vui lòng nhập mã cấp tài khoản',
            'code.min'           => 'Mã tối thiểu 2 ký tự',
            'code.max'           => 'Mã tối đa 50 ký tự',
            'code.unique'        => 'Mã cấp tài khoản đã tồn tại',

            'status.required'    => 'Vui lòng chọn trạng thái',
            'status.in'          => 'Trạng thái không hợp lệ',

            'is_default.boolean' => 'Giá trị default không hợp lệ',
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
