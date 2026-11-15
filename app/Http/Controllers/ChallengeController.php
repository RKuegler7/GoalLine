<?php

namespace App\Http\Controllers;

use App\Atomic;
use App\ChallengeTemplates;
use App\Lent;
use App\Models\Challenge;
use App\Models\ChallengeCheckIn;
use App\Models\ChallengeDispute;
use App\Models\ChallengeMember;
use App\Models\League;
use App\Models\RoadmapTask;
use App\Notifications\ChallengeJoined;
use App\Services\ChallengeBuilder;
use App\Services\ChallengeProgress;
use App\Services\ProofImage;
use App\Services\StoredPhoto;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Challenges: the builder (templates or "Build your own"), the Challenge page with each type's check-in, joining with a
 * link, code or in-app invite (with the rules shown first), owner tools, proof and leaving. Scored by completion
 * (ChallengeProgress), never stars, and never part of GoalLeague standings.
 */
class ChallengeController extends Controller
{
    /**
     * Milestones that earn the celebration: streaks of 7 and 30, halfway, and complete.
     */
    private const STREAK_MILESTONES = [7, 30];

    public function create(Request $request): View
    {
        $league = $request->filled('league') ? League::find($request->integer('league')) : null;

        if ($league) {
            Gate::authorize('manage', $league);
        }

        return view('challenges.builder', [
            'challenge' => null,
            'values' => ChallengeBuilder::defaults($request->query('template')),
            'templates' => ChallengeTemplates::all(),
            'league' => $league,
            'locked' => false,
        ]);
    }

    public function store(Request $request, ChallengeBuilder $builder): RedirectResponse
    {
        $attributes = $builder->attributes($request);
        $league = $request->filled('league_id') ? League::find($request->integer('league_id')) : null;

        if ($league) {
            Gate::authorize('manage', $league);
            // A side Challenge is for the GoalLeague's members only, never listed publicly.
            $attributes['league_id'] = $league->id;
            $attributes['is_public'] = false;
        }

        $challenge = new Challenge($attributes);
        $challenge->creator()->associate($request->user());
        $challenge->target_days = 1;
        $challenge->save();
        $challenge->forceFill(['target_days' => max(1, $challenge->totalPeriods() - $challenge->allowed_misses)])->save();
        $challenge->members()->create(['user_id' => $request->user()->id]);

        return redirect()->route('challenges.show', $challenge)->with('status', $league
            ? 'Side Challenge on. Members of '.$league->name.' can opt in from the GoalLeague page.'
            : 'Challenge on. Invite people, or share the link.');
    }

    public function edit(Challenge $challenge): View
    {
        return view('challenges.builder', [
            'challenge' => $challenge,
            'values' => [
                'template' => $challenge->template,
                'title' => $challenge->title,
                'description' => $challenge->description,
                'type' => $challenge->type,
                'duration' => 'custom',
                'rules' => $challenge->rules ?? ['items' => [$challenge->title]],
                'mode' => $challenge->mode,
                'allowed_misses' => $challenge->allowed_misses,
                'sundays_off' => $challenge->sundays_off,
                'starts_on' => $challenge->starts_on->toDateString(),
                'ends_on' => $challenge->ends_on->toDateString(),
                'month' => $challenge->starts_on->format('Y-m'),
                'lent_year' => Lent::yearFor(Challenge::today()),
                'visibility' => $challenge->is_public ? 'public' : 'private',
                'max_members' => $challenge->max_members,
            ],
            'templates' => ChallengeTemplates::all(),
            'league' => $challenge->league,
            'locked' => $challenge->rulesLocked(),
        ]);
    }

    public function update(Request $request, Challenge $challenge, ChallengeBuilder $builder): RedirectResponse
    {
        $locked = $challenge->rulesLocked();
        $attributes = $builder->attributes($request, $locked);

        if ($challenge->league_id) {
            $attributes['is_public'] = false;
        }

        $challenge->fill($attributes)->save();

        if (! $locked) {
            $challenge->forceFill(['target_days' => max(1, $challenge->totalPeriods() - $challenge->allowed_misses)])->save();
        }

        return redirect()->route('challenges.show', $challenge)->with('status', $locked
            ? 'Saved. The rules are locked now that it has started.'
            : 'Saved.');
    }

    /**
     * The Challenge: today's check-in for its type, your progress, the Standings, members and invites.
     */
    public function show(Request $request, Challenge $challenge): View
    {
        $challenge->load('members.user', 'checkIns', 'creator', 'league');
        $me = $request->user();
        $standings = $challenge->standings();
        $column = in_array($request->query('col'), ['completed', 'percent', 'streak', 'bestStreak', 'misses', 'verified'], true) ? $request->query('col') : null;

        return view('challenges.show', [
            'challenge' => $challenge,
            'standings' => $standings,
            'mine' => $standings->first(fn (array $row) => $row['member']->user_id === $me->id),
            'column' => $column,
            'today' => Challenge::today(),
            'me' => $me,
            'disputes' => $challenge->creator_id === $me->id
                ? ChallengeDispute::where('status', ChallengeDispute::OPEN)
                    ->whereHas('checkIn', fn ($checkIns) => $checkIns->where('challenge_id', $challenge->id))
                    ->with('checkIn.user', 'disputer')->get()
                : collect(),
            'pendingInvites' => $challenge->creator_id === $me->id ? $challenge->invites()->whereNull('accepted_at')->whereNull('declined_at')->with('invitee')->get() : collect(),
        ]);
    }

    /**
     * One member's Challenge, day by day: the sheet the Standings rows open (or its own page).
     */
    public function member(Request $request, Challenge $challenge, ChallengeMember $member): View
    {
        abort_unless($member->challenge_id === $challenge->id, 404);
        $challenge->load('checkIns', 'members.user');
        $row = $challenge->standings()->first(fn (array $row) => $row['member']->is($member));

        return view($request->ajax() ? 'challenges.partials.member' : 'challenges.member', [
            'challenge' => $challenge,
            'member' => $member->load('user'),
            'row' => $row,
            'checkIns' => $challenge->checkIns->where('user_id', $member->user_id)->sortByDesc('checked_on')->values(),
            'me' => $request->user(),
        ]);
    }

    /**
     * Look up a Challenge by the code someone shared.
     */
    public function find(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:20']]);
        $challenge = Challenge::findByCode($request->input('code'));

        return $challenge
            ? redirect()->route('challenges.join', $challenge->invite_code)
            : back()->withErrors(['challenge_code' => 'No Challenge with that code. Check it and try again.'])->withInput();
    }

    /**
     * The join page: what the Challenge is and its rules, to confirm before joining.
     */
    public function join(Request $request, string $code): View|RedirectResponse
    {
        $challenge = Challenge::findByCode($code) ?? abort(404);
        abort_unless($challenge->creator->sharesWorldWith($request->user()), 404);

        if ($challenge->members()->where('user_id', $request->user()->id)->exists()) {
            return redirect()->route('challenges.show', $challenge);
        }

        return view('challenges.join', [
            'challenge' => $challenge->load('creator', 'league')->loadCount('members'),
            'blocker' => $this->joinBlocker($challenge, $request),
        ]);
    }

    public function joinStore(Request $request, string $code): RedirectResponse
    {
        $challenge = Challenge::findByCode($code) ?? abort(404);
        abort_unless($challenge->creator->sharesWorldWith($request->user()), 404);
        $request->validate(['agree' => ['accepted']], ['agree.accepted' => 'Tick the box to say you\'re in for these rules.']);

        if ($blocker = $this->joinBlocker($challenge, $request)) {
            return redirect()->route('challenges.join', $challenge->invite_code)->with('status', $blocker);
        }

        $member = Atomic::exclusively("challenge-join:{$challenge->id}", function () use ($challenge, $request) {
            // Re-checked under the lock, so two people can't take the last place.
            return $challenge->isFull() && ! $challenge->members()->where('user_id', $request->user()->id)->exists()
                ? null
                : $challenge->members()->firstOrCreate(['user_id' => $request->user()->id]);
        }, wait: 5);

        if (! $member) {
            return redirect()->route('challenges.join', $challenge->invite_code)->with('status', 'That Challenge just filled up.');
        }

        $challenge->invites()->where('invitee_id', $request->user()->id)->whereNull('accepted_at')->update(['accepted_at' => now()]);

        if ($member->wasRecentlyCreated && ! $challenge->creator->is($request->user())) {
            $challenge->creator->notify(new ChallengeJoined($challenge->withoutRelations(), $request->user()));
        }

        return redirect()->route('challenges.show', $challenge)->with('status', $challenge->hasStarted()
            ? "You're in. Check in today."
            : "You're in. It starts ".$challenge->starts_on->format('l, M j').'.');
    }

    /**
     * Check in for today or yesterday, by type:
     * - done: one tap (Daily with one rule, Weekly), "Stayed on track" (Abstain);
     * - rule: tick one rule of a Daily checklist;
     * - slipped: "Slipped today" (Abstain);
     * - value: a number (Target), with a plausible-range check;
     * - undo: take the day's check-in back.
     * All checked on the server: members only, inside the Challenge, never a future day, never older than yesterday.
     */
    public function checkIn(Request $request, Challenge $challenge): RedirectResponse
    {
        $user = $request->user();
        abort_unless($challenge->members()->where('user_id', $user->id)->exists(), 403);
        $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'do' => ['nullable', 'in:done,rule,slipped,value,undo'],
            'rule' => ['nullable', 'integer', 'min:0', 'max:7'],
        ]);
        $day = Challenge::day($request->input('date') ?: Challenge::today()->toDateString());
        abort_unless($challenge->isCheckableOn($day), 403, $challenge->hasStarted() ? 'Check-ins for that day are closed.' : 'This Challenge hasn\'t started yet.');

        $action = $request->input('do', 'done');
        $value = null;

        if ($action === 'value') {
            abort_unless($challenge->type === Challenge::TARGET, 422);
            $rules = $challenge->rules ?? [];
            $max = (float) ($rules['max'] ?? 1_000_000);
            $value = (float) $request->validate(['value' => ['required', 'numeric', 'min:0', 'max:'.$max]], [
                'value.max' => 'That\'s more than anyone could log in a day ('.number_format($max).' '.($rules['unit'] ?? '').'). Check the number.',
            ])['value'];

            // Plausible but unusual: ask once, the same way a plan task's result does.
            if ($value > (float) ($rules['threshold'] ?? 0) * 5 && ! $request->boolean('confirmed')) {
                return $this->back($request, $challenge)->withInput()->with('challenge_confirm', ['value' => $value, 'date' => $day->toDateString()]);
            }
        }

        $before = ChallengeProgress::for($challenge, $challenge->checkIns()->where('user_id', $user->id)->get());

        Atomic::run(function () use ($challenge, $user, $day, $action, $request, $value) {
            $checkIn = $challenge->checkIns()->where('user_id', $user->id)->whereDate('checked_on', $day)->lockForUpdate()->first();

            if ($checkIn?->status === ChallengeCheckIn::VOIDED) {
                abort(403, 'This check-in was taken off after a dispute.');
            }

            match ($action) {
                'undo' => $checkIn?->delete(),
                'rule' => $this->toggleRule($challenge, $checkIn, $user->id, $day, (int) $request->input('rule')),
                'slipped' => $checkIn
                    ? $checkIn->forceFill(['status' => ChallengeCheckIn::SLIPPED])->save()
                    : $challenge->checkIns()->create(['user_id' => $user->id, 'checked_on' => $day, 'status' => ChallengeCheckIn::SLIPPED]),
                'value' => $value <= 0
                    ? $checkIn?->delete()
                    : ($checkIn
                        ? $checkIn->forceFill(['status' => ChallengeCheckIn::DONE, 'value' => $value])->save()
                        : $challenge->checkIns()->create(['user_id' => $user->id, 'checked_on' => $day, 'status' => ChallengeCheckIn::DONE, 'value' => $value])),
                default => $checkIn && $challenge->checkInCompletes($checkIn)
                    ? $checkIn->delete()
                    : ($checkIn
                        ? $checkIn->forceFill(['status' => ChallengeCheckIn::DONE, 'rules_done' => $challenge->type === Challenge::DAILY ? array_keys($challenge->checklist()) : null])->save()
                        : $challenge->checkIns()->create(['user_id' => $user->id, 'checked_on' => $day, 'status' => ChallengeCheckIn::DONE, 'rules_done' => $challenge->type === Challenge::DAILY ? array_keys($challenge->checklist()) : null])),
            };
        });

        $after = ChallengeProgress::for($challenge, $challenge->checkIns()->where('user_id', $user->id)->get());

        return $this->back($request, $challenge)
            ->with('status', $this->statusFor($challenge, $action, $after))
            ->with('band_up', $this->milestone($challenge, $before, $after));
    }

    /**
     * Proof for a day's check-in: a photo, cleaned of location and other metadata. Added the same day, it earns the
     * Verified check.
     */
    public function proof(Request $request, Challenge $challenge, ProofImage $images): RedirectResponse
    {
        $user = $request->user();
        abort_unless($challenge->members()->where('user_id', $user->id)->exists(), 403);
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'photo' => ['required', 'file', 'max:5120', 'mimes:jpg,jpeg,png,webp'],
        ], ['photo.max' => 'Photos can be up to 5 MB.']);
        $day = Challenge::day($validated['date']);
        abort_unless($challenge->isCheckableOn($day), 403, 'Proof for that day is closed.');
        $checkIn = $challenge->checkIns()->where('user_id', $user->id)->whereDate('checked_on', $day)->where('status', ChallengeCheckIn::DONE)->first();
        abort_unless($checkIn, 422, 'Check in first, then add proof.');

        try {
            $clean = $images->clean($request->file('photo'));
        } catch (Throwable) {
            return $this->back($request, $challenge)->withErrors(['photo' => "That file doesn't look like a JPG, PNG, or WebP image."]);
        }

        $disk = Storage::disk(RoadmapTask::proofDisk());
        $path = "challenge-proofs/{$challenge->id}/{$checkIn->id}-".Str::random(12).'.'.$clean['extension'];
        $disk->put($path, $clean['bytes']);
        $old = $checkIn->proof_path;
        $checkIn->forceFill(['proof_path' => $path, 'proof_mime_type' => StoredPhoto::typeFor($path), 'proof_uploaded_at' => now()])->save();

        if ($old) {
            $disk->delete($old);
        }

        return $this->back($request, $challenge)->with('status', $checkIn->isVerified() ? 'Proof added. Verified.' : 'Proof added.');
    }

    /**
     * A check-in's proof photo, for members of the Challenge only.
     */
    public function proofPhoto(Request $request, Challenge $challenge, ChallengeCheckIn $checkIn, StoredPhoto $photos): Response
    {
        abort_unless($checkIn->challenge_id === $challenge->id && $checkIn->proof_path, 404);

        return $photos->serve(RoadmapTask::proofDisk(), $checkIn->proof_path, $checkIn->proof_mime_type, ['Cache-Control' => 'private, max-age=3600']);
    }

    /**
     * Leave a Challenge. Your check-ins leave with you.
     */
    public function leave(Request $request, Challenge $challenge): RedirectResponse
    {
        abort_if($challenge->creator_id === $request->user()->id, 403, 'You started this Challenge. Call it off instead.');
        $challenge->members()->where('user_id', $request->user()->id)->delete();
        $challenge->checkIns()->where('user_id', $request->user()->id)->delete();

        return redirect()->route('leagues.index', ['tab' => 'challenges'])->with('status', "You left \u{201C}{$challenge->title}\u{201D}.");
    }

    /**
     * The owner removes a member (and their check-ins).
     */
    public function removeMember(Challenge $challenge, ChallengeMember $member): RedirectResponse
    {
        abort_unless($member->challenge_id === $challenge->id && $member->user_id !== $challenge->creator_id, 404);
        $challenge->checkIns()->where('user_id', $member->user_id)->delete();
        $member->delete();

        return redirect()->to(route('challenges.show', $challenge).'#members')->with('status', 'Removed from the Challenge.');
    }

    /**
     * Call off a Challenge before it starts.
     */
    public function destroy(Challenge $challenge): RedirectResponse
    {
        $challenge->delete();

        return redirect()->route('leagues.index', ['tab' => 'challenges'])->with('status', 'Challenge called off.');
    }

    private function joinBlocker(Challenge $challenge, Request $request): ?string
    {
        return match (true) {
            $challenge->isOver() => 'This Challenge is over. Start your own, or find one that\'s starting soon.',
            $challenge->league_id !== null && ! $challenge->league->members()->where('user_id', $request->user()->id)->exists() => 'This side Challenge is for members of '.$challenge->league->name.'.',
            $challenge->isFull() => 'This Challenge is full.',
            default => null,
        };
    }

    private function toggleRule(Challenge $challenge, ?ChallengeCheckIn $checkIn, int $userId, CarbonInterface $day, int $rule): void
    {
        abort_unless($challenge->type === Challenge::DAILY && array_key_exists($rule, $challenge->checklist()), 422);
        $done = collect($checkIn?->rules_done ?? []);
        $done = $done->contains($rule) ? $done->reject(fn ($index) => $index === $rule) : $done->push($rule);

        if ($done->isEmpty()) {
            $checkIn?->delete();

            return;
        }

        $values = ['status' => ChallengeCheckIn::DONE, 'rules_done' => $done->unique()->sort()->values()->all()];
        $checkIn
            ? $checkIn->forceFill($values)->save()
            : $challenge->checkIns()->create(['user_id' => $userId, 'checked_on' => $day] + $values);
    }

    /**
     * @param  array<string, mixed>  $after
     */
    private function statusFor(Challenge $challenge, string $action, array $after): string
    {
        return match ($action) {
            'undo' => 'Check-in undone.',
            'slipped' => 'Logged. One day doesn\'t undo the others. Back on track tomorrow.',
            default => $after['thisPeriod'] && $challenge->isWeekly()
                ? $after['thisPeriod']['count'].' of '.$after['thisPeriod']['needed'].' this week.'
                : 'Checked in.',
        };
    }

    /**
     * The celebration for a milestone this check-in just reached: a 7- or 30-day streak, halfway, or complete.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<string, mixed>|null
     */
    private function milestone(Challenge $challenge, array $before, array $after): ?array
    {
        $unit = $after['unit'];
        $title = match (true) {
            $after['finished'] && ! $before['finished'] => 'Challenge complete.',
            $after['percent'] >= 50 && $before['percent'] < 50 => 'Halfway there.',
            in_array($after['streak'], self::STREAK_MILESTONES, true) && $after['streak'] > $before['streak'] => $after['streak'].'-'.$unit.' streak.',
            default => null,
        };

        return $title === null ? null : [
            'band' => 'challenge', 'label' => $title, 'percent' => '', 'needsProof' => false, 'goalUrl' => route('challenges.show', $challenge),
            'title' => $title,
            'detail' => $after['completed'].' of '.$after['total'].' '.Str::plural($unit, $after['total']).' in '.$challenge->title.'.',
        ];
    }

    private function back(Request $request, Challenge $challenge): RedirectResponse
    {
        return match ($request->input('return')) {
            'gameplan' => redirect()->to(route('dashboard').'#gameplan'),
            'today' => redirect()->route('today'),
            'list' => redirect()->route('leagues.index', ['tab' => 'challenges']),
            default => redirect()->route('challenges.show', $challenge),
        };
    }
}
