<?php
namespace App\Http\Requests\DealerProfile;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StoreDealerRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tier_id'          => [
                'required',
                'integer',
                Rule::exists('tiers', 'id')->where(fn ($q) => $q->where('status', 'actived')),
            ],
            'company_name'     => ['required', 'string', 'min:2', 'max:255'],
            'company_address'  => ['required', 'string', 'min:5', 'max:500'],
            'tax_code'         => ['required', 'string', 'min:5', 'max:50'],
        ];
    }

    public function messages(): array
    {
        return [
            'tier_id.required'         => 'Vui lòng nhập Tier',
            'tier_id.integer'          => 'Tier không hợp lệ',
            'tier_id.exists'           => 'Tier đã chọn không tồn tại hoặc đã tắt',

            'company_name.required'    => 'Vui lòng nhập tên công ty',
            'company_name.min'         => 'Tên công ty tối thiểu 2 ký tự',
            'company_name.max'         => 'Tên công ty tối đa 255 ký tự',

            'company_address.required' => 'Vui lòng nhập địa chỉ công ty',
            'company_address.min'      => 'Địa chỉ công ty tối thiểu 5 ký tự',
            'company_address.max'      => 'Địa chỉ công ty tối đa 500 ký tự',

            'tax_code.required'        => 'Vui lòng nhập mã số thuế',
            'tax_code.min'             => 'Mã số thuế tối thiểu 5 ký tự',
            'tax_code.max'             => 'Mã số thuế tối đa 50 ký tự',
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
