<?php
namespace App\Http\Requests\Discount;

use App\Models\Discount;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdateDiscountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'des'         => ['required', 'string', 'min:2', 'max:255'],
            'percent'     => ['required', 'numeric', 'min:1', 'max:100'],
            'status'      => ['nullable', Rule::in(['actived', 'disabled'])],
            'start_at'    => ['required', 'date'],
            'end_at'      => ['required', 'date', 'after_or_equal:start_at'],
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.required' => 'Vui long chon danh muc',
            'category_id.exists'   => 'Danh muc khong hop le',
            'des.required'         => 'Vui long nhap mo ta khuyen mai',
            'des.min'              => 'Mo ta khuyen mai toi thieu 2 ky tu',
            'des.max'              => 'Mo ta khuyen mai toi da 255 ky tu',
            'percent.required'     => 'Vui long nhap phan tram khuyen mai',
            'percent.numeric'      => 'Phan tram khuyen mai phai la so',
            'percent.min'          => 'Phan tram khuyen mai phai tu 1% den 100%',
            'percent.max'          => 'Phan tram khuyen mai phai tu 1% den 100%',
            'status.in'            => 'Trang thai khong hop le',
            'start_at.required'    => 'Vui long chon ngay bat dau',
            'start_at.date'        => 'Ngay bat dau khong hop le',
            'end_at.required'      => 'Vui long chon ngay ket thuc',
            'end_at.date'          => 'Ngay ket thuc khong hop le',
            'end_at.after_or_equal'=> 'Ngay ket thuc phai lon hon hoac bang ngay bat dau',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $discountId = (int) ((string) $this->route('discount') ?: (string) $this->route('id'));
            $categoryId = (int) $this->input('category_id');
            $startAt = (string) $this->input('start_at');
            $endAt = (string) $this->input('end_at');

            if (! $categoryId || $startAt === '' || $endAt === '') {
                return;
            }

            $hasOverlap = Discount::query()
                ->where('category_id', $categoryId)
                ->where('id', '!=', $discountId)
                ->whereDate('start_at', '<=', $endAt)
                ->whereDate('end_at', '>=', $startAt)
                ->exists();

            if ($hasOverlap) {
                $message = 'Danh muc nay da co khuyen mai trong khoang thoi gian da chon';
                $validator->errors()->add('start_at', $message);
                $validator->errors()->add('end_at', $message);
            }
        });
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Loi xac thuc du lieu',
            'errors'  => $validator->errors(),
        ], 422));
    }
}
