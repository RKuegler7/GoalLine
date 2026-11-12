{{-- A friendly dead end that isn't one: why this invite can't be used right now, and where to go instead. $reason is
     expired, full, over or missing. --}}
@php
    [$title, $text] = match ($reason) {
        'expired' => ['This invite has expired or was turned off.', 'Invite links can be used up or switched off by the league owner. Ask '.($league ? Str::before($league->commissioner->name, ' ') : 'whoever sent it').' for a fresh one.'],
        App\Services\LeagueJoin::FULL => ['This GoalLeague is full.', 'It already has the most members a league can hold. You can start your own with the same kind of goal in a minute.'],
        App\Services\LeagueJoin::OVER => ['This season is over.', 'The matchups are done for this one. Start your own GoalLeague, or ask the owner about running it back.'],
        default => ['We couldn\'t find that invite.', 'The link may be mistyped, or the league was called off. Double-check it, or ask for a new one.'],
    };
@endphp

<div data-join-closed="{{ $reason }}">
    <p class="font-serif text-xl text-club tracking-tight">{{ $title }}</p>
    <p class="mt-2 text-ink/70">{{ $text }}</p>
    <div class="mt-5 flex flex-wrap gap-3">
        @auth
            <a href="{{ route('leagues.create') }}" class="inline-flex min-h-11 items-center rounded-full bg-club px-5 font-semibold text-ivory hover:bg-club-dark">Start your own GoalLeague</a>
            <a href="{{ route('leagues.index', ['tab' => 'invites']) }}#join" class="inline-flex min-h-11 items-center rounded-full border border-club/40 px-5 font-semibold text-club hover:border-club">Join another with a code</a>
            @if (Route::has('highlights.explore'))
                <a href="{{ route('highlights.index', ['tab' => 'explore']) }}" class="inline-flex min-h-11 items-center rounded-full border border-club/40 px-5 font-semibold text-club hover:border-club">Explore GoalLeagues</a>
            @endif
        @else
            <a href="{{ route('register') }}" class="inline-flex min-h-11 items-center rounded-full bg-club px-5 font-semibold text-ivory hover:bg-club-dark">Create an account</a>
            <a href="{{ route('login') }}" class="inline-flex min-h-11 items-center rounded-full border border-club/40 px-5 font-semibold text-club hover:border-club">Sign in</a>
        @endauth
    </div>
</div>
