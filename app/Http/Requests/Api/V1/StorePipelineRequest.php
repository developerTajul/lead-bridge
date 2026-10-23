<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

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
}
