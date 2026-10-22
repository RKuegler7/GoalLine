<?php

namespace App\Http\Requests;

use App\GoalStatus;
use App\ProfileVisibility;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileRequest extends FormRequest
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
            'email' => ['sometimes', 'required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users')->ignore($this->user())],
            'username' => ['required', 'string', 'min:3', 'max:30', 'regex:/^[a-z0-9._]+$/', Rule::unique('users')->ignore($this->user())],
            'bio' => ['nullable', 'string', 'max:160'],
            'location' => ['nullable', 'string', 'max:80'],
            'timezone' => ['nullable', 'timezone:all'],
            'profile_visibility' => ['sometimes', Rule::enum(ProfileVisibility::class)],
            'featured_goal_id' => [
                'nullable',
                Rule::exists('goals', 'id')->where('user_id', $this->user()->id)->whereIn('status', [GoalStatus::Active->value, GoalStatus::Completed->value]),
            ],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_avatar' => ['nullable', 'boolean'],
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_cover' => ['nullable', 'boolean'],
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
            'username.regex' => 'Use lowercase letters, numbers, dots, and underscores only.',
            'username.unique' => 'That username is taken.',
            'avatar.max' => 'Profile photos can be up to 2 MB.',
            'cover.max' => 'Cover photos can be up to 4 MB.',
            'timezone.timezone' => 'Pick a time zone from the list.',
        ];
    }

    /**
     * Normalize the username before validating it.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['username' => strtolower(ltrim((string) $this->input('username'), '@'))]);
    }
}
