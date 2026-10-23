<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Core\Auth\Domain\Enums\AuthRole;
use Core\Auth\Domain\Enums\AuthStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the input for updating a user.
 *
 * Name, email, role and status are required because the mapper reads all four
 * unconditionally: it falls back to MEMBER and PENDING when either enum is
 * missing, so an update that omitted them would silently demote the account
 * rather than leave it alone. Password and image stay optional — omitting them
 * keeps the stored values.
 */
final class UpdateUserRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $this->route('user')],
            'password' => ['nullable', 'string', 'min:8'],
            'image' => ['nullable', 'image', 'max:2048'],
            'role' => ['required', 'string', Rule::in(AuthRole::values())],
            'status' => ['required', 'string', Rule::in(array_column(AuthStatus::cases(), 'value'))],
        ];
    }
}
