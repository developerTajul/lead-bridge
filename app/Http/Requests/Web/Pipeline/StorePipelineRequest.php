<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Pipeline;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the input for creating a pipeline and its stages.
 */
final class StorePipelineRequest extends FormRequest
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
            'name'                    => ['required', 'string', 'max:255'],
            'is_default'              => ['nullable', 'boolean'],
            'stages'                  => ['required', 'array', 'min:1'],
            'stages.*.name'           => ['required', 'string', 'max:255'],
            'stages.*.order_position' => ['required', 'integer', 'min:1'],
            'stages.*.probability'    => ['required', 'integer', 'min:0', 'max:100'],
        ];
    }

    /**
     * Get the custom validation messages.
     *
     * A form posts the stage rows as a list, so a plain "stages is required"
     * would not tell the user which part of the form to fix.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'stages.required' => 'Add at least one stage to the pipeline.',
            'stages.min' => 'Add at least one stage to the pipeline.',
            'stages.*.name.required' => 'Every stage needs a name.',
            'stages.*.order_position.required' => 'Every stage needs an order position.',
            'stages.*.probability.required' => 'Every stage needs a probability.',
        ];
    }
}
