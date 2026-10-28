<?php

namespace App\Http\Requests;

use App\Services\ProductionOrderService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexProductionOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'product_code' => ['nullable', 'string', 'max:20'],
            'machine_code' => ['nullable', 'string', 'max:10'],
            'status' => ['nullable', Rule::in(ProductionOrderService::STATUSES)],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'sort_by' => ['nullable', Rule::in(array_keys(ProductionOrderService::SORTABLE_COLUMNS))],
            'sort_dir' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.ProductionOrderService::MAX_PER_PAGE],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
