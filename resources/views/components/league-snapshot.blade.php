{{-- A GoalLeague at a glance: your rank, this week's matchup, and unread chat. --}}
@props(['snapshot'])

@php
    $league = $snapshot->league;
    $matchup = $snapshot->matchup();
    $unread = $snapshot->unread();
    $rank = $snapshot->rank();
    $size = $league->members->count();
    $ordinal = $rank.match (true) {
        in_array($rank % 100, [11, 12, 13], true) => 'th',
        $rank % 10 === 1 => 'st',
        $rank % 10 === 2 => 'nd',
        $rank % 10 === 3 => 'rd',
        default => 'th',
    };
@endphp

<article {{ $attributes->class('overflow-hidden rounded-2xl border border-line bg-white') }}>
    <a href="{{ route('leagues.show', $league) }}" class="block px-5 pt-4 hover:bg-mint/30">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="truncate font-serif text-lg font-medium text-club">{{ $league->name }}</p>
                <p class="text-xs text-ink/55">
                    @if ($league->status() === App\LeagueStatus::Forming)
                        Kicks off {{ $league->starts_on->format('M j') }} &middot; {{ $size }} {{ Str::plural('member', $size) }}
                    @elseif ($league->status() === App\LeagueStatus::Active)
                        Week {{ $league->currentWeek() }} of {{ $league->weeksCount() }}
                    @else
                        Season over
                    @endif
                </p>
            </div>
            @if ($league->status() !== App\LeagueStatus::Forming)
                <div class="shrink-0 text-right">
                    <p class="font-serif text-2xl font-medium text-club">{{ $ordinal }}</p>
                    <p class="text-xs text-ink/55">of {{ $size }} &middot; {{ $snapshot->points() }} pts</p>
                </div>
            @endif
        </div>
    </a>

    @if ($matchup)
        <div class="mx-5 mt-3 rounded-xl bg-ivory px-3 py-2.5">
            <p class="eyebrow text-[0.58rem] text-ink/50">This week's GoalGroup</p>
            <div class="mt-1.5 flex items-center justify-between gap-2 text-sm">
                <span class="font-semibold text-ink">You</span>
                <span @class([
                    'rounded-full px-2 py-0.5 text-xs font-semibold',
                    'bg-grass text-white' => $matchup['outcome'] === App\MatchupOutcome::Win,
                    'bg-mint text-club' => $matchup['outcome'] === App\MatchupOutcome::Tie,
                    'bg-red-50 text-red-700' => $matchup['outcome'] === App\MatchupOutcome::Loss,
                ])>
                    {{ match ($matchup['outcome']) { App\MatchupOutcome::Win => 'Leading', App\MatchupOutcome::Tie => 'Level', App\MatchupOutcome::Loss => 'Trailing' } }}
                </span>
                <span class="truncate font-semibold text-ink">{{ $matchup['opponent'] ? Str::before($matchup['opponent']->user->name, ' ') : 'League median' }}</span>
            </div>
            <div class="mt-1 flex items-center justify-between gap-2 text-xs text-ink/60">
                <span>{{ $matchup['mine']['result']?->label() ?? 'No GoalWeek' }} &middot; {{ $matchup['mine']['points'] }}</span>
                <span class="text-right">
                    @if ($matchup['opponent'])
                        {{ $matchup['theirs']['points'] }} &middot; {{ $matchup['theirs']['result']?->label() ?? 'No GoalWeek' }}
                    @else
                        {{ round($matchup['median'] ?? 0, 1) }} pts
                    @endif
                </span>
            </div>
        </div>
    @endif

    <a href="{{ route('leagues.show', $league) }}#chat" class="mt-3 flex items-center justify-between border-t border-line px-5 py-2.5 text-sm hover:bg-mint/30">
        <span class="font-medium text-club">League chat</span>
        @if ($unread > 0)
            <span class="rounded-full bg-club px-2 py-0.5 text-xs font-semibold text-ivory tabular-nums">{{ $unread }} new</span>
        @else
            <span class="text-ink/40">&rarr;</span>
        @endif
    </a>
</article>
