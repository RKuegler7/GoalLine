<?php

namespace App\Http\Controllers;

use App\Notifications\AdminAlert;
use App\Usage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class FeedbackController extends Controller
{
    /**
     * A beta tester's note: saved for the admin page and emailed to the admins.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
            'page' => ['nullable', 'string', 'max:500'],
        ], [
            'body.required' => 'Write a few words first.',
        ]);

        $user = $request->user();
        DB::table('feedback')->insert([
            'user_id' => $user->id,
            'body' => trim($validated['body']),
            'page' => $validated['page'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Usage::track($user, 'feedback');

        if (config('goalline.admin_emails') !== []) {
            Notification::route('mail', config('goalline.admin_emails'))->notify(new AdminAlert(
                "Beta feedback from {$user->name}",
                str($validated['body'])->limit(1500).($validated['page'] ?? null ? "\n\n(On {$validated['page']})" : ''),
            ));
        }

        return back()->with('status', 'Thanks! Your note went straight to the GoalLine team.');
    }
}
