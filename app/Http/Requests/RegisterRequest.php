<?php

namespace App\Http\Requests;

use App\Models\Invite;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
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
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'timezone' => ['nullable', 'timezone:all'],
            'invite' => [$this->needsInvite() ? 'required' : 'nullable', 'string', 'max:20', function (string $attribute, mixed $value, Closure $fail) {
                if ($this->needsInvite() && ! Invite::usable($value)) {
                    $fail('That invite code isn\'t valid or has been used up. Ask whoever invited you for a new one.');
                }
            }],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['invite.required' => 'GoalLine is invite-only during the private beta. Enter your invite code.'];
    }

    /**
     * During the private beta, everyone but the admins needs an invite.
     */
    public function needsInvite(): bool
    {
        return config('goalline.beta.invite_only')
            && ! in_array(strtolower((string) $this->input('email')), array_map('strtolower', config('goalline.admin_emails')), true);
    }
}
