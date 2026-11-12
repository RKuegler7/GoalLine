{{-- A goal in play with no current GoalLeague: invite it into one, or keep it solo against the line. Needs $goal. --}}

<section class="overflow-hidden rounded-xl border border-club/25 bg-white" aria-labelledby="play-{{ $goal->id }}" data-play-with-friends>
    <div class="flex items-start gap-3 bg-mint/60 px-3 py-2.5">
        <x-icon name="leagues" class="mt-0.5 size-5 shrink-0 text-club" />
        <div class="min-w-0">
            <h2 id="play-{{ $goal->id }}" class="text-[15px] font-bold text-club">Play it with friends?</h2>
            <p class="text-[13px] text-ink/75">&ldquo;{{ $goal->title }}&rdquo; isn't in a GoalLeague right now. Each week you'd face a friend, both scored against your own plans.</p>
        </div>
    </div>
    <div class="grid gap-2 px-3 py-3 sm:grid-cols-3">
        <a href="{{ route('leagues.create', ['goal' => $goal->id]) }}" class="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-full bg-club px-4 text-[14px] font-semibold text-ivory hover:bg-club-dark">
            <x-icon name="plus" class="size-4" /> Start a GoalLeague
        </a>
        <a href="{{ route('leagues.index', ['tab' => 'invites']) }}#join" class="inline-flex min-h-11 items-center justify-center rounded-full border border-club/30 px-4 text-[14px] font-semibold text-club hover:border-club">Join with a code</a>
        <form method="POST" action="{{ route('goals.next.choose', $goal) }}">
            @csrf
            <input type="hidden" name="choice" value="solo">
            <input type="hidden" name="from" value="clubhouse">
            <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center rounded-full px-4 text-[14px] font-semibold text-ink/75 hover:text-ink">Keep it solo against the line</button>
        </form>
    </div>
</section>
