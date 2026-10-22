<?php

namespace App\Http\Controllers;

use App\NotificationSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class NotificationSettingsController extends Controller
{
    /**
     * Choose what GoalLine pushes and emails you, and when your daily reminders arrive.
     */
    public function edit(Request $request): View
    {
        return view('settings.notifications', ['settings' => $request->user()->notificationSettings()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['array'],
            'email.*' => ['boolean'],
            'push' => ['array'],
            'push.*' => ['boolean'],
            'morning_at' => ['nullable', Rule::in(NotificationSettings::MORNING_TIMES)],
            'evening_at' => ['nullable', Rule::in(NotificationSettings::EVENING_TIMES)],
        ]);

        $settings = new NotificationSettings(
            email: collect(NotificationSettings::EMAIL_DEFAULTS)->map(fn (bool $default, string $category) => (bool) ($validated['email'][$category] ?? false))->all(),
            push: collect(NotificationSettings::PUSH_DEFAULTS)->map(fn (bool $default, string $category) => (bool) ($validated['push'][$category] ?? false))->all(),
            morningAt: $validated['morning_at'] ?? null,
            eveningAt: $validated['evening_at'] ?? null,
        );

        $request->user()->forceFill(['notification_settings' => $settings->toArray()])->save();

        return redirect()->route('settings.notifications')->with('status', 'Notification settings saved.');
    }
}
