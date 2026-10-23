<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the input for creating a deal.
 */
final class StoreDealRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title'               => ['required', 'string', 'max:255'],
            'company_id'          => ['nullable', 'integer', 'exists:companies,id'],
            'contact_id'          => ['nullable', 'integer', 'exists:contacts,id'],
            'amount'              => ['nullable', 'numeric', 'min:0'],
            'currency'            => ['nullable', 'string', 'size:3'],
            'pipeline_id'         => ['required', 'integer', 'exists:pipelines,id'],
            'deal_stage_id'       => ['required', 'integer', 'exists:deal_stages,id'],
            'expected_close_date' => ['nullable', 'date'],
            'actual_close_date'   => ['nullable', 'date'],
        ];
    }
}
