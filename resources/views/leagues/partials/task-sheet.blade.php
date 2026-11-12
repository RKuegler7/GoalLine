{{-- A task from the matchup pager: details, proof (for the owner, their opponent and the league owner), the post
     shared from it (if the viewer may see it), and the actions. A final week is read only. --}}
@php
    $task = $row['task'];
    $owner = $member->user;
    $viewer = auth()->user();
    $cheered = $highlight?->isCheeredBy($viewer) ?? false;
@endphp

<div class="space-y-4">
    <header>
        <p class="eyebrow text-[11px] text-ink/70">
            {{ $owner->is($viewer) ? 'Your' : Str::before($owner->name, ' ').'’s' }} task &middot;
            {{ $task->planned_on ? $task->planned_on->format('l, M j') : 'Any day this week' }}
        </p>
        <h2 id="task-sheet-title" class="mt-1 pr-10 font-serif text-2xl font-bold text-club tracking-tight">{{ $task->description }}</h2>
        <div class="mt-2 flex flex-wrap items-center gap-1.5 text-xs">
            <span @class([
                'rounded-full px-2 py-0.5 font-semibold',
                'bg-grass text-ink' => $row['status'] === App\Services\MatchupPager::STATUS_DONE,
                'bg-gold/30 text-ink' => $row['status'] === App\Services\MatchupPager::STATUS_TODAY,
                'bg-red-50 text-red-700' => $row['status'] === App\Services\MatchupPager::STATUS_MISSED,
                'bg-ivory text-ink/70 ring-1 ring-inset ring-line' => in_array($row['status'], [App\Services\MatchupPager::STATUS_UPCOMING, App\Services\MatchupPager::STATUS_REST], true),
            ])>{{ $row['statusLabel'] }}</span>
            <span class="rounded-full bg-ivory px-2 py-0.5 font-semibold text-ink/70 tabular-nums ring-1 ring-inset ring-line">{{ $row['points'] }} {{ Str::plural('point', $row['points']) }}</span>
            @if ($row['bonus'])
                <span class="rounded-full bg-gold/25 px-2 py-0.5 font-semibold text-ink">Bonus</span>
            @endif
            @if ($row['group'] || $row['personal'])
                <x-task-tags :task="$task" />
            @endif
            @if ($task->detailsVisibleTo($viewer))
                <x-proof-details :task="$task" />
            @endif
            @if ($row['verified'])
                <span class="rounded-full bg-mint px-2 py-0.5 font-semibold text-club">&check; Verified</span>
            @endif
        </div>
        @if ($task->result_required)
            <p class="mt-2 text-sm text-ink/70">
                Plan: {{ $task->formatNumber($task->result_target) }}
                @if ($row['result'])
                    &middot; Logged: <span class="font-semibold text-ink">{{ $task->formatNumber($task->result_value) }}</span>
                @endif
            </p>
        @endif
        @if ($task->completed_at)
            <p class="mt-1 text-xs text-ink/70">Checked in {{ App\Clock::shortTime($task->completed_at) }}</p>
        @endif
    </header>

    {{-- Proof. --}}
    @if ($row['proofUrl'])
        <figure>
            <img src="{{ $row['proofUrl'] }}" alt="Proof for {{ $task->description }}" loading="lazy" decoding="async" class="max-h-80 w-full rounded-2xl bg-ivory object-contain ring-1 ring-line">
            <figcaption class="mt-1 text-xs text-ink/70">Proof photos open for {{ Str::before($owner->name, ' ') }}, their matchup opponent and the league owner.</figcaption>
        </figure>
    @elseif ($row['verified'])
        <p class="rounded-xl bg-mint/50 px-4 py-3 text-sm text-ink/75">This check-in has proof. The photo opens for {{ Str::before($owner->name, ' ') }}, their matchup opponent and the league owner.</p>
    @endif

    {{-- The post shared from this check-in. --}}
    @if ($highlight)
        <section class="rounded-2xl border border-line px-4 py-3">
            <p class="eyebrow text-[11px] text-ink/70">Shared to the feed</p>
            @if ($highlight->headline)
                <p class="mt-1 font-semibold text-ink">{{ $highlight->headline }}</p>
            @endif
            @if ($highlight->caption)
                <p class="mt-1 text-sm whitespace-pre-line text-ink/80">{{ $highlight->caption }}</p>
            @endif
            <div class="mt-3 flex flex-wrap items-center gap-3">
                <form method="POST" action="{{ route('highlights.cheer', $highlight) }}" data-live data-optimistic="cheer" data-live-key="cheer-{{ $highlight->id }}">
                    @csrf
                    <input type="hidden" name="intent" value="{{ $cheered ? 'remove' : 'add' }}">
                    <button type="submit" aria-pressed="{{ $cheered ? 'true' : 'false' }}" @class([
                        'flex min-h-11 items-center gap-1.5 rounded-full px-4 text-sm font-semibold transition',
                        'bg-grass text-ink' => $cheered,
                        'bg-mint text-club hover:bg-grass/20' => ! $cheered,
                    ])>
                        <x-icon name="highlights" class="size-4" />
                        {{ $cheered ? 'Cheered' : 'Cheer' }}
                        <span class="tabular-nums" data-optimistic-count="{{ $highlight->cheers->count() }}">{{ $highlight->cheers->count() }}</span>
                    </button>
                </form>
                <a href="{{ route('leagues.feed', [$league, 'member' => $owner->id]) }}#highlight-{{ $highlight->id }}" class="text-sm font-semibold text-club hover:underline">See the post</a>
            </div>
        </section>
    @endif

    {{-- Actions. --}}
    @if ($readOnly)
        <p class="rounded-xl bg-ivory px-4 py-3 text-sm text-ink/65">GoalWeek {{ $week }} is final, so this is read only.</p>
    @elseif ($task->completed_at)
        <section class="space-y-2">
            @if ($isOwner && ! $row['verified'])
                <a href="{{ route('goals.show', $goalWeek->goal) }}#goalweek-{{ $goalWeek->position }}" class="flex min-h-11 items-center justify-center rounded-full bg-club px-5 text-sm font-semibold text-ivory hover:bg-club-dark">Show your work: add a photo</a>
            @elseif (! $isOwner && ! $row['verified'])
                @if ($task->proofRequests->contains('requester_id', $viewer->id))
                    <p class="text-sm text-ink/70">You asked {{ Str::before($owner->name, ' ') }} to show their work.</p>
                @else
                    <form method="POST" action="{{ route('roadmap.tasks.proof.request', $task) }}" data-live>
                        @csrf
                        <button type="submit" class="flex min-h-11 w-full items-center justify-center rounded-full border border-club/40 px-5 text-sm font-semibold text-club hover:border-club">Show your work?</button>
                    </form>
                @endif
            @endif
        </section>
    @elseif ($isOwner)
        <a href="{{ route('goals.show', $goalWeek->goal) }}#goalweek-{{ $goalWeek->position }}" class="flex min-h-11 items-center justify-center rounded-full bg-club px-5 text-sm font-semibold text-ivory hover:bg-club-dark">Check it in on your plan</a>
    @endif

    @if ($task->completed_at)
        <div class="text-sm">
            <x-dispute-controls :task="$task" :league="$league" :owner="$owner" :can-dispute="$canDispute" />
        </div>
    @endif
</div>
