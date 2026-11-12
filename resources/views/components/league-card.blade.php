{{-- One GoalLeague in a list: name, your goal, the week, your record, this week's band and progress, and the matchup.
     One tap opens the league. --}}
@props(['snapshot'])

@php
    $league = $snapshot->league;
    $status = $league->status();
    $size = $league->members->count();
    $matchup = $status === App\LeagueStatus::Active ? $snapshot->matchup() : null;
    $mine = $matchup['mine'] ?? null;
    $record = $snapshot->record();
    $unread = $snapshot->unread();
    $result = $mine['result'] ?? null;
    $tone = match ($result) {
        App\GoalWeekResult::Short => 'short',
        App\GoalWeekResult::Past, App\GoalWeekResult::Smashed => 'gold',
        default => 'grass',
    };
    $goal = $snapshot->me->goal;
@endphp

<a href="{{ route('leagues.show', $league) }}" data-league-card {{ $attributes->class('block rounded-xl border border-line bg-white px-3 py-2.5 transition hover:border-club/40') }}>
    <div class="flex items-start justify-between gap-2">
        <div class="min-w-0">
            <p class="truncate text-[15px] font-bold text-club">{{ $league->name }}</p>
            <p class="truncate text-xs text-ink/70">{{ $goal?->title ?? 'Pick your goal for this league' }}</p>
        </div>
        <div class="flex shrink-0 items-center gap-1.5">
            @if ($unread > 0)
                <span class="rounded-full bg-club px-1.5 py-0.5 text-[11px] font-semibold text-ivory tabular-nums" title="Unread chat messages">{{ $unread }} new</span>
            @endif
            @if ($status === App\LeagueStatus::Active)
                <x-goalweek-result :result="$result ?? App\GoalWeekResult::Short" :not-started="$result === null || ($mine['percent'] ?? 0) === 0" compact />
            @else
                <span @class(['eyebrow rounded-full px-2 py-0.5 text-[11px]', 'bg-mint text-club' => $status === App\LeagueStatus::Forming, 'bg-ivory text-ink/70 ring-1 ring-line' => $status === App\LeagueStatus::Finished])>{{ $status->label() }}</span>
            @endif
        </div>
    </div>

    @if ($status === App\LeagueStatus::Active)
        <x-progress-bar :percent="$mine['percent'] ?? 0" :tone="$tone" class="mt-2" label="This GoalWeek: {{ $mine['percent'] ?? 0 }}% of plan" />
    @endif

    <p class="mt-1.5 flex flex-wrap items-center gap-x-2 text-xs text-ink/70">
        @if ($status === App\LeagueStatus::Forming)
            <span>Kicks off {{ $league->starts_on->format('D, M j') }}</span>
            <span aria-hidden="true">&middot;</span>
            <span>{{ $size }} {{ Str::plural('member', $size) }}</span>
            @if ($size < App\Models\League::INVITE_PROMPT_BELOW && $league->isJoinable())
                <span class="rounded-full bg-gold px-2 py-0.5 text-[11px] font-semibold text-ink">Invite friends</span>
            @endif
        @else
            <span class="font-semibold text-ink">{{ $status === App\LeagueStatus::Active ? 'Week '.$league->currentWeek().' of '.$league->weeksCount() : 'Season over' }}</span>
            <span aria-hidden="true">&middot;</span>
            <span class="tabular-nums"><span class="font-semibold text-ink">{{ $snapshot->ordinal() }}</span> of {{ $size }}</span>
            <span aria-hidden="true">&middot;</span>
            <span class="tabular-nums">{{ $record['wins'] }}&ndash;{{ $record['ties'] }}&ndash;{{ $record['losses'] }}</span>
            @if ($mine)
                <span aria-hidden="true">&middot;</span>
                <span class="tabular-nums">{{ $mine['percent'] }}%</span>
            @endif
        @endif
    </p>
    @if ($matchup)
        {{-- Encouraging, never alarming: leading shows green, everything else stays neutral. --}}
        <p class="mt-0.5 truncate text-xs text-ink/70"><span class="sr-only">GoalWeek {{ $league->currentWeek() }} Matchup: </span>{{ $matchup['opponent'] ? 'vs '.Str::before($matchup['opponent']->user->name, ' ') : 'Bye week' }}:
            <span @class(['font-semibold', 'text-club' => $matchup['outcome']->isWin(), 'text-ink' => ! $matchup['outcome']->isWin()])>{{ match ($matchup['outcome']) {
                App\MatchupOutcome::Win => 'leading',
                App\MatchupOutcome::CloseWin => 'ahead in a photo finish',
                App\MatchupOutcome::Tie => 'level',
                App\MatchupOutcome::CloseLoss => 'just behind, photo finish',
                App\MatchupOutcome::Loss => 'chasing',
                App\MatchupOutcome::Bye => 'line stars only',
            } }}</span>
        </p>
    @endif
</a>
