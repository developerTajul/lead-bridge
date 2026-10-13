<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Core\Activity\Domain\Enums\ActivityType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates incoming activity creation data.
 */
final class ActivityStoreRequest extends FormRequest
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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in(ActivityType::values())],
            'description' => ['nullable', 'string'],
            'due_date' => ['nullable', 'date'],
            'trackable_type' => ['required', 'string', 'max:255'],
            'trackable_id' => ['required', 'integer', 'min:1'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'completed_at' => ['nullable', 'date'],
        ];
    }
}