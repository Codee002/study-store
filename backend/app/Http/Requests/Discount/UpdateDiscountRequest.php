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
            'category_id.required' => 'Vui lòng chọn danh mục',
            'category_id.exists'   => 'Danh mục không hợp lệ',
            'des.required'         => 'Vui lòng nhập mô tả khuyến mãi',
            'des.min'              => 'Mô tả khuyến mãi tối thiểu 2 ký tự',
            'des.max'              => 'Mô tả khuyến mãi tối đa 255 ký tự',
            'percent.required'     => 'Vui lòng nhập phần trăm khuyến mãi',
            'percent.numeric'      => 'Phần trăm khuyến mãi phải là số',
            'percent.min'          => 'Phần trăm khuyến mãi phải từ 1% đến 100%',
            'percent.max'          => 'Phần trăm khuyến mãi phải từ 1% đến 100%',
            'status.in'            => 'Trạng thái không hợp lệ',
            'start_at.required'    => 'Vui lòng chọn ngày bắt đầu',
            'start_at.date'        => 'Ngày bắt đầu không hợp lệ',
            'end_at.required'      => 'Vui lòng chọn ngày kết thúc',
            'end_at.date'          => 'Ngày kết thúc không hợp lệ',
            'end_at.after_or_equal'=> 'Ngày kết thúc phải lớn hơn hoặc bằng ngày bắt đầu',
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
                $message = 'Danh mục này đã có khuyến mãi trong khoảng thời gian đã chọn';
                $validator->errors()->add('start_at', $message);
                $validator->errors()->add('end_at', $message);
            }
        });
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Lỗi xác thực dữ liệu',
            'errors'  => $validator->errors(),
        ], 422));
    }
}
