<?php

declare(strict_types=1);

namespace App\Http\Requests\Lead;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates incoming lead capture data.
 */
final class LeadCaptureRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'max:100'],
        ];
    }
}
