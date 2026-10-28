<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductionResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'wo_number' => ['required', 'string', 'exists:work_order,wo_number'],
            'actual_start' => ['required', 'date_format:Y-m-d H:i:s', 'before_or_equal:now'],
            'actual_finish' => ['required', 'date_format:Y-m-d H:i:s', 'after:actual_start', 'before_or_equal:now'],
            'good_qty' => ['required', 'integer', 'min:0'],
            'reject_qty' => ['required', 'integer', 'min:0'],
        ];
    }
}
