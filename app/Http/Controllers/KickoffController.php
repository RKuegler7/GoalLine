<?php

namespace App\Http\Controllers;

use App\Usage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class KickoffController extends Controller
{
    /**
     * Hide the Kickoff checklist on the Clubhouse.
     */
    public function dismiss(Request $request): RedirectResponse
    {
        $request->user()->forceFill(['kickoff_dismissed_at' => now()])->save();

        return redirect()->route('dashboard');
    }

    /**
     * The app reports that it's running from the home screen. Recorded once per member.
     */
    public function installed(Request $request): Response
    {
        $already = DB::table('usage_events')->where('user_id', $request->user()->id)->where('name', 'app-installed')->exists();

        if (! $already) {
            Usage::track($request->user(), 'app-installed');
        }

        return response()->noContent();
    }
}
