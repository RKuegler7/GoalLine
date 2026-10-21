<?php

namespace App\Http\Requests;

use App\GoalCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GoalRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:120'],
            'category' => ['required', Rule::enum(GoalCategory::class)],
            'target_date' => ['required', 'date', 'after:today'],
            'why' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Name the goal you\'re going after.',
            'category.required' => 'Pick the kind of goal this is.',
            'target_date.required' => 'Set the date this needs to happen by.',
            'target_date.after' => 'Pick a date after today.',
        ];
    }
}
