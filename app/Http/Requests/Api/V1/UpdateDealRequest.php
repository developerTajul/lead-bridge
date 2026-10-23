<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the input for updating a deal. Every field is optional, so a client
 * can send only the ones it means to change.
 *
 * The actual close date is not a field a client sets: the stage the deal moves
 * to decides it, so a payload carrying one is refused rather than silently
 * ignored.
 */
final class UpdateDealRequest extends FormRequest
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
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            // A deal cannot be unlinked from its company or its contact by
            // clearing the field: an absent key leaves the link alone, and a
            // blank one is a client error the mapper reports.
            'company_id' => ['sometimes', 'required', 'integer', 'exists:companies,id'],
            'contact_id' => ['sometimes', 'required', 'integer', 'exists:contacts,id'],
            'amount' => ['sometimes', 'required', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'required', 'string', 'size:3'],
            'pipeline_id' => ['sometimes', 'required', 'integer', 'exists:pipelines,id'],
            'deal_stage_id' => ['sometimes', 'required', 'integer', 'exists:deal_stages,id'],
            'expected_close_date' => ['sometimes', 'nullable', 'date'],
            'actual_close_date' => ['prohibited'],
        ];
    }
}
