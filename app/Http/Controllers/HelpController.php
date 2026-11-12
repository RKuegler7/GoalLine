<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HelpController extends Controller
{
    /**
     * Which FAQ answer to open first, by the screen Help was opened from.
     *
     * @var array<string, string>
     */
    public const CONTEXTS = [
        'leagues.standings' => 'table',
        'leagues.standings.player' => 'table',
        'leagues.show' => 'bands',
        'leagues.matchups' => 'reading-matchup',
        'matchups' => 'reading-matchup',
        'goals.show' => 'how-plans-work',
        'goals.refine' => 'refine',
        'goals.intake' => 'how-plans-work',
        'planner.show' => 'how-plans-work',
        'today' => 'when-check-in',
        'dashboard' => 'when-check-in',
        'leagues.join' => 'join',
        'leagues.invite.show' => 'join',
        'leagues.my-goal' => 'join',
        'highlights.index' => 'who-sees-posts',
        'profile.edit' => 'settings',
    ];

    /**
     * The Help center: a searchable FAQ, the tour, scoring, and feedback. Inside the app it's fetched into the Help
     * sheet; opened directly (no scripts) it's a page of its own. ?context= opens the most relevant answer.
     */
    public function show(Request $request): View
    {
        $faq = require resource_path('help/faq.php');
        $focus = collect($faq)->flatMap(fn (array $group) => array_column($group['items'], 'id'))->contains($request->query('context'))
            ? $request->query('context')
            : null;

        return view($request->ajax() ? 'help.panel' : 'help.index', [
            'faq' => $faq,
            'focus' => $focus,
            'page' => $request->query('page'),
        ]);
    }

    /**
     * The first-run tour was finished or skipped: don't offer it again (it can be replayed from Help).
     */
    public function tourDone(Request $request): JsonResponse
    {
        $request->user()->forceFill(['tour_completed_at' => now()])->save();

        return response()->json(['ok' => true]);
    }
}
