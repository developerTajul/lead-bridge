<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the input for updating a contact. Every field is optional, so a
 * client can send only the ones it means to change.
 */
final class UpdateContactRequest extends FormRequest
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
     * The unique rule ignores the contact being updated, so resubmitting an
     * unchanged email is not read as a clash with itself.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('contacts', 'email')->ignore($this->contactId()),
            ],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            // A null company_id is how a client asks to detach the contact, so
            // only a supplied value has to name a company that exists.
            'company_id' => ['sometimes', 'nullable', 'integer', 'exists:companies,id'],
            'job_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_primary' => ['sometimes', 'nullable', 'boolean'],
        ];
    }

    /**
     * The ID of the contact being updated, read from the route binding.
     *
     * @return int|null
     */
    private function contactId(): ?int
    {
        $contact = $this->route('contact');

        if ($contact === null) {
            return null;
        }

        return (int) (is_object($contact) ? $contact->id : $contact);
    }
}
