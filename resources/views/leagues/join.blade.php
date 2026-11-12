{{-- Joining a GoalLeague, from an invite link (works signed out) or a typed code. --}}
@php
    $viewer = auth()->user();
    $expired = $invite && ! $invite->isUsable();
    $chosenGoal = (int) old('goal_id', request()->integer('goal'));
    $inviter = $invite?->creator ?? $league->commissioner;
    $memberCount = $league->members->count();
    $summary = collect([
        $league->category?->label().($league->category ? ' goals' : null),
        $memberCount.' '.Str::plural('member', $memberCount),
        $league->weeksCount().'-week season',
    ])->filter()->implode(' · ');
@endphp

@push('head')
    @include('partials.share-meta', [
        'title' => "Join {$league->name} on GoalLine",
        'description' => Str::before($inviter->name, ' ')." invited you to a GoalLeague: {$summary}. Bring one real goal and play a friendly weekly matchup.",
        'url' => $invite ? $invite->url() : App\ShareLink::route('leagues.join', $league->invite_code),
    ])
@endpush

<x-dynamic-component :component="$viewer ? 'layouts.app' : 'layouts.field'" :title="'Join '.$league->name.' · GoalLine'">
    <x-slot:hero>
        <div class="mx-auto max-w-5xl px-4 pt-6 pb-12">
            <p class="eyebrow text-mint">{{ Str::before($inviter->name, ' ') }} invited you</p>
            <h1 class="mt-3 font-serif text-4xl font-bold tracking-tight text-ivory sm:text-5xl">{{ $league->name }}</h1>
            @if ($league->hasGoal())
                <p class="mt-3 flex items-center gap-2 text-lg font-semibold text-ivory" data-league-goal>
                    <x-icon name="flag" class="size-5 shrink-0 text-gold-light" /> Goal: {{ $league->goalSummary() }}
                </p>
            @endif
            @if ($league->description)
                <p class="mt-3 max-w-xl text-ivory/85">{{ $league->description }}</p>
            @endif
            <p class="mt-3 text-sm text-ivory/85">
                @if ($league->category)
                    {{ $league->category->label() }} &middot;
                @endif
                Run by {{ $league->commissioner->name }} &middot; {{ $league->members->count() }} {{ Str::plural('member', $league->members->count()) }} &middot;
                {{ $league->weeksCount() }}-week season, {{ $league->starts_on->format('M j') }} to {{ $league->ends_on->format('M j, Y') }}
            </p>
        </div>
    </x-slot:hero>

    @php
        $blocker = App\Services\LeagueJoin::blocker($league);
    @endphp
    <div @class(['mx-auto max-w-2xl rounded-2xl border border-line bg-white p-4 sm:p-6', 'my-10 mx-4 sm:mx-auto' => ! $viewer])>
        @if ($expired || $blocker !== null)
            @include('leagues.partials.join-closed', ['reason' => $expired ? 'expired' : $blocker])
        @elseif (! $viewer)
            <p class="font-serif text-xl text-club tracking-tight">Sign in to join.</p>
            <p class="mt-2 text-ink/70">Each member brings one goal{{ $league->hasGoal() ? ', and you can start from the league goal' : '' }}. Every GoalWeek your progress goes head-to-head with a league mate's. New to GoalLine? This invite gets you into the private beta, and brings you right back here.</p>
            <div class="mt-5 flex flex-wrap gap-3">
                <a href="{{ route('register') }}" class="rounded-full bg-club px-6 py-2.5 font-semibold text-ivory hover:bg-club-dark">Create an account</a>
                <a href="{{ route('login') }}" class="rounded-full border border-club/40 px-6 py-2.5 font-semibold text-club hover:border-club">Sign in</a>
            </div>
        @elseif (! $viewer->hasVerifiedEmail())
            <p class="font-serif text-xl text-club tracking-tight">One step first: verify your email.</p>
            <p class="mt-2 text-ink/70">We sent a link to {{ $viewer->email }}. Open it and you'll come right back here to pick your goal. This invite is saved.</p>
            <form method="POST" action="{{ route('verification.send') }}" class="mt-4">
                @csrf
                <button type="submit" class="rounded-full border border-club/40 px-5 py-2 text-sm font-semibold text-club hover:border-club">Send the link again</button>
            </form>
        @else
            <h2 class="font-serif text-xl text-club tracking-tight">Set the goal you'll play</h2>
            <p class="mt-1 mb-4 text-sm text-ink/70">
                @if ($league->status() === App\LeagueStatus::Active)
                    The season is under way. You'll play from GoalWeek {{ $league->currentWeek() }}, and your plan runs from today; the weeks before you joined don't count against you.
                @else
                    Kickoff is {{ $league->starts_on->format('l, F j') }}.
                @endif
            </p>
            @include('leagues.partials.join-goal-step')

            <ul class="mt-5 flex flex-wrap gap-2" aria-label="Members">
                @foreach ($league->members as $member)
                    <li class="flex items-center gap-2 rounded-full bg-ivory py-1 pr-3 pl-1 text-sm text-ink/80">
                        <x-avatar :user="$member->user" class="size-6 text-[11px]" /> {{ $member->user->name }}
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-dynamic-component>
