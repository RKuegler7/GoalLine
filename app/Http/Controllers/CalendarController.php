<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CalendarFeed;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CalendarController extends Controller
{
    /**
     * The calendar feed. Calendar apps can't log in, so the secret token in the link is the key.
     */
    public function feed(string $token): Response
    {
        $user = User::where('calendar_token', $token)->firstOrFail();

        return response((new CalendarFeed($user))->render(), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="goalline.ics"',
            'Cache-Control' => 'private, max-age=900',
        ]);
    }

    /**
     * Replace the calendar link, for example if it was shared by mistake.
     */
    public function reset(Request $request): RedirectResponse
    {
        $request->user()->resetCalendarToken();

        return redirect()->to(route('settings.notifications').'#calendar')
            ->with('status', 'New calendar link made. The old one no longer works, so re-add GoalLine to your calendar.');
    }
}
