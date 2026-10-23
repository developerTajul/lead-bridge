<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Deal;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the input for updating a deal.
 *
 * The form posts every field it shows, so an emptied company or contact field
 * is the user asking to unlink one rather than a client error. The two are
 * nullable here and the mapper reads a blank as "no link", unlike the API
 * where an absent key means "leave the stored value alone".
 *
 * The actual close date is not a field the form shows: the stage the deal
 * moves to decides it.
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
            'title' => ['required', 'string', 'max:255'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'pipeline_id' => ['required', 'integer', 'exists:pipelines,id'],
            'deal_stage_id' => ['required', 'integer', 'exists:deal_stages,id'],
            'expected_close_date' => ['nullable', 'date'],
        ];
    }

    /**
     * Get the custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Give the deal a title.',
            'amount.required' => 'Give the deal an amount, even if it is zero.',
            'currency.required' => 'Give the deal a three-letter currency code.',
            'pipeline_id.required' => 'Choose the pipeline the deal belongs to.',
            'deal_stage_id.required' => 'Choose the stage the deal sits in.',
        ];
    }
}
