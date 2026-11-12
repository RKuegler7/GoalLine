<?php

namespace App\Http\Requests;

use App\FriendsListVisibility;
use App\GoalStatus;
use App\Models\User;
use App\ProfileVisibility;
use App\Rules\CleanName;
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
            'why_here' => ['nullable', 'string', 'max:80', new CleanName],
            'interests' => ['nullable', 'array', 'max:'.User::MAX_INTERESTS],
            'interests.*' => ['string', 'min:2', 'max:'.User::MAX_INTEREST_LENGTH, new CleanName],
            'location' => ['nullable', 'string', 'max:80'],
            'timezone' => ['nullable', 'timezone:all'],
            'profile_visibility' => ['sometimes', Rule::enum(ProfileVisibility::class)],
            'discoverable' => ['sometimes', 'boolean'],
            'friends_list_visibility' => ['sometimes', Rule::enum(FriendsListVisibility::class)],
            'public_cards' => ['sometimes', 'boolean'],
            'featured_goal_id' => [
                'nullable',
                Rule::exists('goals', 'id')->where('user_id', $this->user()->id)->whereIn('status', [GoalStatus::Active->value, GoalStatus::Completed->value]),
            ],
            'avatar' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:5120'],
            'remove_avatar' => ['nullable', 'boolean'],
            'cover' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:5120'],
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
            'avatar.max' => 'Profile photos can be up to 5 MB.',
            'cover.max' => 'Cover photos can be up to 5 MB.',
            'avatar.mimes' => 'Use a JPG, PNG, or WebP image.',
            'avatar.mimetypes' => 'Use a JPG, PNG, or WebP image.',
            'cover.mimes' => 'Use a JPG, PNG, or WebP image.',
            'cover.mimetypes' => 'Use a JPG, PNG, or WebP image.',
            'why_here.max' => 'Keep "Why I\'m here" to 80 characters.',
            'interests.max' => 'Pick up to '.User::MAX_INTERESTS.' interests.',
            'interests.*.max' => 'Keep each interest to '.User::MAX_INTEREST_LENGTH.' characters.',
            'interests.*.min' => 'Interests need at least 2 characters.',
            'timezone.timezone' => 'Pick a time zone from the list.',
        ];
    }

    /**
     * Normalize the username before validating it.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['username' => strtolower(ltrim((string) $this->input('username'), '@'))]);

        // Interests come as chosen tags and/or a comma-separated "add your own" box: one tidy, de-duplicated list.
        if ($this->has('interests') || $this->filled('interests_other')) {
            $tags = collect((array) $this->input('interests', []))
                ->merge(explode(',', (string) $this->input('interests_other')))
                ->map(fn ($tag) => trim(preg_replace('/\s+/', ' ', (string) $tag)))
                ->filter()
                ->unique(fn ($tag) => mb_strtolower($tag))
                ->values()
                ->all();

            $this->merge(['interests' => $tags]);
        }
    }
}
