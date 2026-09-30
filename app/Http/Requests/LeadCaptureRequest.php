<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LeadCaptureRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // হেডারে পাঠানো X-CRM-KEY ভ্যালিডেট করা (আপাতত env/config থেকে চেক)
        $clientKey = $this->header('X-CRM-KEY');
        $validKey = config('services.crm.api_key', 'test_secret_key_123');

        return !empty($clientKey) && hash_equals($validKey, $clientKey);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name'         => ['required', 'string', 'max:255'],
            'email'        => ['nullable', 'email', 'max:255'],
            'phone'        => ['nullable', 'string', 'max:50'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'source'       => ['nullable', 'string', 'max:100'],
        ];
    }
}