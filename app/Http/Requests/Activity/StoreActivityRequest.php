<?php

namespace App\Http\Requests\Activity;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreActivityRequest extends FormRequest
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
            'trackable_type' => ['required', 'string', 'max:255'],
            'trackable_id' => ['required', 'integer'],
            'type' => ['required', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:255'],
            'due_date' => ['required', 'date'],
        ];
    }
}
