<?php

namespace App\Http\Controllers;

use App\Models\League;
use App\Models\LeagueInvite;
use App\Models\LeagueMember;
use App\Models\User;
use App\Notifications\LeagueInvited;
use App\Notifications\LeagueInviteEmail;
use App\Notifications\LeagueMemberJoined;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LeagueInviteController extends Controller
{
    /**
     * The page an invite link opens: the league, who runs it, and how many are in. Visitors sign in or create an
     * account first (the invite lets them past the private beta), then pick or set the goal they'll play.
     */
    public function show(Request $request, string $token): View|RedirectResponse
    {
        $invite = LeagueInvite::where('token', $token)->with('league.members.user', 'league.commissioner')->first();
        abort_unless($invite, 404);
        $league = $invite->league;
        $user = $request->user();
        abort_if($user && ! $league->commissioner->sharesWorldWith($user), 404);

        if ($user && $league->memberFor($user)) {
            return redirect()->route('leagues.show', $league);
        }

        if ($invite->isUsable()) {
            // Signing in from here comes straight back.
            if (! $user) {
                $request->session()->put('url.intended', $invite->url());
            }

            // Remembered through sign-up and sign-in, and for coming back after setting a goal.
            $request->session()->put('league_invite', $token);
            $request->session()->put('league_join_return', $invite->url());
        }

        return view('leagues.join', [
            'league' => $league,
            'invite' => $invite,
            'joinAction' => route('leagues.invite.join', $token),
            'goals' => $user?->goals()->active()->orderBy('target_date')->get() ?? collect(),
        ]);
    }

    /**
     * Join through an invite with one of your active goals.
     */
    public function join(Request $request, string $token): RedirectResponse
    {
        $invite = LeagueInvite::findUsable($token);
        abort_unless($invite, 410, 'This invite has expired or been turned off. Ask for a new one.');
        $league = $invite->league->load('members');
        $user = $request->user();
        abort_unless($league->commissioner->sharesWorldWith($user), 404);

        if ($league->memberFor($user)) {
            return redirect()->route('leagues.show', $league);
        }

        abort_unless($league->isJoinable(), 403, 'This league has already kicked off or is full.');

        $validated = $request->validate([
            'goal_id' => ['required', Rule::exists('goals', 'id')->where('user_id', $user->id)->where('status', 'active')],
        ], [
            'goal_id.required' => 'Pick the goal you will play in this league.',
            'goal_id.exists' => 'Pick one of your active goals.',
        ]);

        DB::transaction(function () use ($invite, $league, $user, $validated) {
            if (! $invite->claim()) {
                throw ValidationException::withMessages(['goal_id' => 'This invite was just used up. Ask for a new one.']);
            }

            $league->members()->create(['user_id' => $user->id, 'goal_id' => $validated['goal_id']]);
        });

        $request->session()->forget(['league_invite', 'league_join_return']);
        $league->announce($user->name.' joined the league.');
        if (! $league->commissioner->is($user)) {
            $league->commissioner->notify(new LeagueMemberJoined($league->withoutRelations(), $user));
        }

        $status = "You're in {$league->name}. Kickoff is {$league->starts_on->format('l, F j')}.";

        return $user->onboarded_at
            ? redirect()->route('leagues.show', $league)->with('status', $status)
            : redirect()->route('welcome.crew')->with('status', $status);
    }

    /**
     * Invite someone by email or username. Members can invite; it's a one-time invite that expires in 14 days.
     */
    public function store(Request $request, League $league): RedirectResponse
    {
        $validated = $request->validateWithBag('invite', ['who' => ['required', 'string', 'max:255']], [
            'who.required' => 'Enter an email address or a username.',
        ]);
        $who = trim($validated['who']);
        $isEmail = filter_var($who, FILTER_VALIDATE_EMAIL) !== false;
        $person = ($isEmail
            ? User::whereRaw('lower(email) = ?', [strtolower($who)])
            : User::whereRaw('lower(username) = ?', [strtolower(ltrim($who, '@'))]))
            ->where('demo_sandbox', $request->user()->demo_sandbox)
            ->first();

        $problem = match (true) {
            ! $league->isJoinable() => 'This league has kicked off, so new members can\'t join this season.',
            $isEmail && $request->user()->isDemo() => 'Email invites are turned off in the demo. Invite one of your demo friends by @username.',
            ! $isEmail && ! $person => 'No one has that username. Check the spelling, or invite them by email.',
            $person && $league->memberFor($person) !== null => "{$person->name} is already in the league.",
            default => null,
        };

        if ($problem) {
            throw ValidationException::withMessages(['who' => $problem])->errorBag('invite');
        }

        $invite = $league->invites()->create([
            'kind' => $person ? LeagueInvite::USERNAME : LeagueInvite::EMAIL,
            'email' => $person ? null : strtolower($who),
            'invitee_id' => $person?->id,
            'expires_at' => now()->addDays(LeagueInvite::DEFAULT_EXPIRY_DAYS),
            'max_uses' => 1,
        ]);
        $invite->forceFill(['created_by' => $request->user()->id])->save();

        if ($person) {
            $person->notify(new LeagueInvited($invite, $request->user()));
        }

        if ($isEmail) {
            Notification::route('mail', strtolower($who))->notify(new LeagueInviteEmail($invite, $request->user()));
        }

        return redirect()->to(route('leagues.show', $league).'#invite')->with('status', $isEmail ? 'Invite emailed to '.strtolower($who).'.' : "Invited {$person->name}. They'll see it in their inbox.");
    }

    /**
     * Owner only: replace the shareable link (the old one stops working), with an expiry and an optional use cap.
     */
    public function regenerate(Request $request, League $league): RedirectResponse
    {
        Gate::authorize('manage', $league);
        $validated = $request->validate([
            'expires_in_days' => ['nullable', Rule::in(array_filter(LeagueInvite::EXPIRY_CHOICES))],
            'max_uses' => ['nullable', 'integer', 'min:1', 'max:'.League::MAX_MEMBERS],
            'new_code' => ['nullable', 'boolean'],
        ]);

        $league->invites()->where('kind', LeagueInvite::LINK)->whereNull('revoked_at')->update(['revoked_at' => now()]);
        $invite = $league->invites()->create([
            'kind' => LeagueInvite::LINK,
            'expires_at' => filled($validated['expires_in_days'] ?? null) ? now()->addDays((int) $validated['expires_in_days']) : null,
            'max_uses' => $validated['max_uses'] ?? null,
        ]);
        $invite->forceFill(['created_by' => $request->user()->id])->save();

        if ($request->boolean('new_code')) {
            $league->regenerateInviteCode();
        }

        return redirect()->to(route('leagues.show', $league).'#invite')->with('status', 'New invite link ready. The old one no longer works.');
    }

    /**
     * Owner only: turn off an invite.
     */
    public function revoke(Request $request, LeagueInvite $invite): RedirectResponse
    {
        Gate::authorize('manage', $invite->league);

        $invite->forceFill(['revoked_at' => now()])->save();

        return redirect()->to(route('leagues.show', $invite->league).'#invite')->with('status', 'Invite turned off.');
    }

    /**
     * Owner only: remove a member from the league.
     */
    public function removeMember(Request $request, League $league, LeagueMember $member): RedirectResponse
    {
        Gate::authorize('manage', $league);
        abort_unless($member->league_id === $league->id, 404);
        abort_if($member->user_id === $league->commissioner_id, 422, 'The league owner stays in the league.');

        $name = $member->user->name;
        $member->delete();
        $league->announce("{$name} is no longer in the league.");

        return redirect()->to(route('leagues.show', $league).'#invite')->with('status', "Removed {$name} from the league.");
    }
}
