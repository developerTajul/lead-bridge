<?php

declare(strict_types=1);

namespace App\Http\Requests\Web\Contact;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the query input for listing contacts.
 *
 * Only the shape is checked here: the mapper decides what a usable filter is,
 * so an unusable value is dropped by the controller and reported on the page
 * rather than as a framework validation payload.
 */
final class IndexContactRequest extends FormRequest
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
            'search' => ['nullable', 'string'],
            'per_page' => ['nullable'],
        ];
    }
}
