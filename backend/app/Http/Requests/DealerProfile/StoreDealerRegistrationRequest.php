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
            'tier_id.required'         => 'Vui lÃ²ng chá»n loáº¡i tier',
            'tier_id.integer'          => 'Tier khÃ´ng há»£p lá»‡',
            'tier_id.exists'           => 'Tier Ä‘Ã£ chá»n khÃ´ng tá»“n táº¡i hoáº·c Ä‘Ã£ táº¯t',

            'company_name.required'    => 'Vui lÃ²ng nháº­p tÃªn cÃ´ng ty',
            'company_name.min'         => 'TÃªn cÃ´ng ty tá»‘i thiá»ƒu 2 kÃ½ tá»±',
            'company_name.max'         => 'TÃªn cÃ´ng ty tá»‘i Ä‘a 255 kÃ½ tá»±',

            'company_address.required' => 'Vui lÃ²ng nháº­p Ä‘á»‹a chá»‰ cÃ´ng ty',
            'company_address.min'      => 'Äá»‹a chá»‰ cÃ´ng ty tá»‘i thiá»ƒu 5 kÃ½ tá»±',
            'company_address.max'      => 'Äá»‹a chá»‰ cÃ´ng ty tá»‘i Ä‘a 500 kÃ½ tá»±',

            'tax_code.required'        => 'Vui lÃ²ng nháº­p mÃ£ sá»‘ thuáº¿',
            'tax_code.min'             => 'MÃ£ sá»‘ thuáº¿ tá»‘i thiá»ƒu 5 kÃ½ tá»±',
            'tax_code.max'             => 'MÃ£ sá»‘ thuáº¿ tá»‘i Ä‘a 50 kÃ½ tá»±',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Dá»¯ liá»‡u khÃ´ng há»£p lá»‡',
            'errors'  => $validator->errors(),
        ], 422));
    }
}
