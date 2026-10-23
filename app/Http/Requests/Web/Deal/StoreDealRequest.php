<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Deal;

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
     * An emptied company or contact field is a valid submission meaning "no
     * link", so those two are nullable. A pipeline and a stage cannot be left
     * out: a deal always sits in one of each.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'pipeline_id' => ['required', 'integer', 'exists:pipelines,id'],
            'deal_stage_id' => ['required', 'integer', 'exists:deal_stages,id'],
            'expected_close_date' => ['nullable', 'date'],
        ];
    }

    /**
     * Get the custom validation messages.
     *
     * The form is filled by picking from lists, so a bare "the pipeline id
     * field is required" would not tell the user which control to look at.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Give the deal a title.',
            'pipeline_id.required' => 'Choose the pipeline the deal belongs to.',
            'deal_stage_id.required' => 'Choose the stage the deal sits in.',
        ];
    }
}
