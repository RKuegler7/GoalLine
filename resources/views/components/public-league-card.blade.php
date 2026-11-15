{{-- A public GoalLeague anyone can join, in Club Green: the same card in Explore, the GoalLeagues tab's "Find a public
     GoalLeague" row and its full list. Name, category, members and the cap, kickoff or the week, length and the
     owner's name. Join goes through the usual join flow (the goal step, with the league goal filled in). --}}
@props(['league', 'member' => false, 'example' => false])

<li {{ $attributes->class('flex flex-col justify-between rounded-2xl border border-line bg-white px-4 py-3') }} data-public-league>
    <div>
        <p class="eyebrow text-[11px] text-ink/70">
            {{ App\Http\Controllers\ExploreController::categoryLabel($league->category) }}
            @if ($example) &middot; Example @endif
        </p>
        <p class="mt-0.5 text-[16px] font-bold text-club [overflow-wrap:anywhere]">{{ $league->name }}</p>
        <p class="text-[13px] text-ink/75">{{ $league->members_count }} of {{ App\Models\League::MAX_MEMBERS }} &middot; {{ App\Http\Controllers\ExploreController::weekLabel($league) }} &middot; {{ $league->weeksCount() }} {{ Str::plural('week', $league->weeksCount()) }}</p>
        <p class="text-[13px] text-ink/75">Run by {{ $league->commissioner?->name }}</p>
        @if ($league->description)
            <p class="mt-1 line-clamp-2 text-[13px] text-ink/80">{{ $league->description }}</p>
        @endif
    </div>
    @if ($member)
        <a href="{{ route('leagues.show', $league) }}" class="mt-2 inline-flex min-h-11 items-center justify-center rounded-full border border-club/30 px-4 text-sm font-semibold text-club">You're in · Open</a>
    @else
        <form method="POST" action="{{ route('explore.join', $league) }}" class="mt-2">
            @csrf
            <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center rounded-full bg-grass px-4 text-sm font-bold whitespace-nowrap text-ink hover:brightness-105" data-join-public>Join</button>
        </form>
    @endif
</li>
