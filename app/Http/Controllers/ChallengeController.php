<?php

namespace App\Http\Controllers;

use App\Clock;
use App\Models\Challenge;
use App\Notifications\ChallengeJoined;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ChallengeController extends Controller
{
    /**
     * Start a challenge.
     */
    public function create(): View
    {
        return view('challenges.create', ['today' => Clock::today()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $today = Clock::today();
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:500'],
            'starts_on' => ['required', 'date', 'after_or_equal:'.$today->toDateString(), 'before_or_equal:'.$today->copy()->addDays(60)->toDateString()],
            'length' => ['required', 'integer', Rule::in(Challenge::LENGTHS)],
            'target_days' => ['required', 'integer', 'min:1', 'lte:length'],
        ], [
            'title.required' => 'Name the challenge, like "No sugar for 30 days".',
            'starts_on.after_or_equal' => 'Start today or later.',
            'target_days.lte' => 'The target can be at most the number of days in the challenge.',
        ]);

        $startsOn = Clock::today()->setDateFrom($request->date('starts_on'));

        $challenge = new Challenge([
            'title' => trim($validated['title']),
            'description' => filled($validated['description'] ?? null) ? trim($validated['description']) : null,
            'starts_on' => $startsOn,
            'ends_on' => $startsOn->copy()->addDays($validated['length'] - 1),
            'target_days' => $validated['target_days'],
        ]);
        $challenge->creator()->associate($request->user())->save();
        $challenge->members()->create(['user_id' => $request->user()->id]);

        return redirect()->route('challenges.show', $challenge)->with('status', 'Challenge on. Send the invite link to bring people in.');
    }

    /**
     * The challenge: today's check-in, your progress, and the leaderboard.
     */
    public function show(Request $request, Challenge $challenge): View
    {
        $challenge->load('members.user', 'checkIns', 'creator');

        return view('challenges.show', [
            'challenge' => $challenge,
            'standings' => $challenge->standings(),
            'today' => Clock::today(),
            'me' => $request->user(),
        ]);
    }

    /**
     * Look up a challenge by the code someone shared.
     */
    public function find(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:20']]);
        $challenge = Challenge::findByCode($request->input('code'));

        return $challenge
            ? redirect()->route('challenges.join', $challenge->invite_code)
            : back()->withErrors(['challenge_code' => 'No challenge with that code. Check it and try again.'])->withInput();
    }

    /**
     * A preview before joining from an invite link.
     */
    public function join(Request $request, string $code): View|RedirectResponse
    {
        $challenge = Challenge::findByCode($code) ?? abort(404);
        abort_unless($challenge->creator->sharesWorldWith($request->user()), 404);

        if ($challenge->members()->where('user_id', $request->user()->id)->exists()) {
            return redirect()->route('challenges.show', $challenge);
        }

        return view('challenges.join', ['challenge' => $challenge->load('creator')->loadCount('members')]);
    }

    public function joinStore(Request $request, string $code): RedirectResponse
    {
        $challenge = Challenge::findByCode($code) ?? abort(404);
        abort_unless($challenge->creator->sharesWorldWith($request->user()), 404);
        abort_if($challenge->isOver(), 403, 'This challenge is over.');

        $member = $challenge->members()->firstOrCreate(['user_id' => $request->user()->id]);

        if ($member->wasRecentlyCreated && ! $challenge->creator->is($request->user())) {
            $challenge->creator->notify(new ChallengeJoined($challenge->withoutRelations(), $request->user()));
        }

        return redirect()->route('challenges.show', $challenge)->with('status', "You're in. Check in every day you do it.");
    }

    /**
     * Check in for today (or yesterday, within the grace), or undo it.
     */
    public function checkIn(Request $request, Challenge $challenge): RedirectResponse
    {
        $day = $request->filled('date') ? Clock::today()->setDateFrom($request->date('date')) : Clock::today();
        abort_unless($challenge->isCheckableOn($day), 403, 'Check-ins for that day are closed.');

        $existing = $challenge->checkIns()->where('user_id', $request->user()->id)->whereDate('checked_on', $day)->first();
        $existing ? $existing->delete() : $challenge->checkIns()->create(['user_id' => $request->user()->id, 'checked_on' => $day]);

        return match ($request->input('return')) {
            'gameplan' => redirect()->to(route('dashboard').'#gameplan'),
            'list' => redirect()->route('leagues.index'),
            default => redirect()->route('challenges.show', $challenge),
        };
    }

    /**
     * Leave a challenge. Your check-ins leave with you.
     */
    public function leave(Request $request, Challenge $challenge): RedirectResponse
    {
        $challenge->members()->where('user_id', $request->user()->id)->delete();
        $challenge->checkIns()->where('user_id', $request->user()->id)->delete();

        return redirect()->route('leagues.index')->with('status', "You left \u{201C}{$challenge->title}\u{201D}.");
    }

    /**
     * Call off a challenge before it starts.
     */
    public function destroy(Challenge $challenge): RedirectResponse
    {
        $challenge->delete();

        return redirect()->route('leagues.index')->with('status', 'Challenge called off.');
    }
}
