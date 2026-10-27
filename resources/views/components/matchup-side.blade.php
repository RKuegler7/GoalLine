{{-- One side of a GoalGroup matchup: who, their GoalWeek result, and the outcome. --}}
@props(['member', 'result', 'outcome', 'final', 'align' => 'left'])

<div @class(['flex min-w-0 flex-1 items-center gap-3', 'flex-row-reverse text-right' => $align === 'right'])>
    <x-avatar :user="$member->user" class="size-9 text-xs" />
    <div class="min-w-0">
        <p class="truncate font-medium text-ink">{{ $member->user->name }}</p>
        <div @class(['mt-1 flex flex-wrap items-center gap-1.5', 'justify-end' => $align === 'right'])>
            @if ($result['result'])
                <x-goalweek-result :result="$result['result']" :not-started="$result['goalWeek']?->isNotStarted() ?? false" :needs-proof="$result['goalWeek']?->needsProofForSmashed() ?? false" />
            @else
                <span class="text-xs text-ink/50">No GoalWeek yet</span>
            @endif
            @if ($final)
                <span @class([
                    'flex size-6 items-center justify-center rounded-md text-xs font-bold',
                    'bg-grass text-white' => $outcome === App\MatchupOutcome::Win,
                    'bg-mint text-club' => $outcome === App\MatchupOutcome::Tie,
                    'bg-red-100 text-red-700' => $outcome === App\MatchupOutcome::Loss,
                ]) title="{{ ucfirst($outcome->value) }}">{{ $outcome->letter() }}</span>
            @endif
        </div>
    </div>
</div>
