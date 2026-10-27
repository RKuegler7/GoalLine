{{-- Both sides' check-ins for a league week, visible to the whole league. Proof photos open for the opponent and the
     league owner; any league mate can ask someone to show their work; the opponent can confirm a Smashed week. --}}
@props(['league', 'week', 'matchup', 'open' => false])

@php
    $viewer = auth()->user();
    $from = $league->weekStartsOn($week)->copy()->startOfDay();
    $to = $league->weekEndsOn($week)->copy()->endOfDay();
    $sides = collect([[$matchup['home'], $matchup['homeResult'], $matchup['away']], [$matchup['away'], $matchup['awayResult'], $matchup['home']]])
        ->filter(fn ($side) => $side[0] !== null);
    $rows = $sides->map(function ($side) use ($from, $to) {
        [$member, $result, $other] = $side;
        $tasks = ($result['goalWeek']?->tasks ?? new Illuminate\Database\Eloquent\Collection)
            ->filter(fn ($task) => $task->completed_at && App\Clock::local($task->completed_at)->between($from, $to))
            ->sortByDesc('completed_at')
            ->values();
        $tasks->load('proofRequests');

        return ['member' => $member, 'goalWeek' => $result['goalWeek'], 'other' => $other, 'tasks' => $tasks];
    });
    $total = $rows->sum(fn ($row) => $row['tasks']->count());
@endphp

<details class="mt-3 border-t border-line pt-2" @if ($open) open @endif>
    <summary class="cursor-pointer list-none text-xs font-semibold text-club hover:underline [&::-webkit-details-marker]:hidden">
        This week's check-ins ({{ $total }})
    </summary>
    <div class="mt-2 grid gap-4 sm:grid-cols-2">
        @foreach ($rows as $row)
            @php
                $owner = $row['member']->user;
                $isOpponent = $row['other']?->user_id === $viewer->id;
                $canSeeProof = $owner->is($viewer) || $isOpponent || $league->commissioner_id === $viewer->id;
            @endphp
            <div class="min-w-0">
                <p class="text-xs font-semibold text-ink">{{ $owner->name }}</p>

                @if ($row['goalWeek']?->needsProofForSmashed())
                    <div class="mt-1.5 rounded-lg bg-sky-50 px-2.5 py-2 text-xs text-sky-900 ring-1 ring-sky-200 ring-inset">
                        Reached Smashed. Needs proof or an opponent's OK to count in full.
                        @if ($isOpponent && ! $row['goalWeek']->isLocked())
                            <form method="POST" action="{{ route('goalweeks.confirm-smashed', $row['goalWeek']) }}" class="mt-1.5">
                                @csrf
                                <button type="submit" class="rounded-full bg-sky-600 px-3 py-1 text-xs font-semibold text-white hover:bg-sky-700">Confirm their Smashed week</button>
                            </form>
                        @endif
                    </div>
                @elseif ($row['goalWeek']?->smashed_confirmed_at)
                    <p class="mt-1 text-xs text-sky-800">Smashed week confirmed by their opponent.</p>
                @endif

                @if ($row['tasks']->isEmpty())
                    <p class="mt-1.5 text-xs text-ink/50">No check-ins yet this week. One more day on the line.</p>
                @else
                    <ol class="mt-1.5 space-y-1.5 border-l-2 border-mint pl-3">
                        @foreach ($row['tasks'] as $task)
                            <li class="text-xs">
                                <span class="text-ink/45 tabular-nums">{{ App\Clock::shortTime($task->completed_at) }}</span>
                                <span class="text-ink">{{ $task->description }}</span>
                                @if ($task->result_required && $task->result_value !== null)
                                    <span class="text-ink/60 tabular-nums">&middot; {{ $task->formatNumber($task->result_value) }}</span>
                                @endif
                                @if ($task->isVerified())
                                    @if ($canSeeProof)
                                        <a href="{{ route('roadmap.tasks.proof', $task) }}" target="_blank" rel="noopener" class="ml-1 inline-flex items-center gap-0.5 rounded-full bg-mint px-1.5 font-semibold text-club hover:bg-grass/20">&check; Verified</a>
                                    @else
                                        <span class="ml-1 inline-flex items-center gap-0.5 rounded-full bg-mint px-1.5 font-semibold text-club">&check; Verified</span>
                                    @endif
                                @elseif (! $owner->is($viewer))
                                    @if ($task->proofRequests->contains('requester_id', $viewer->id))
                                        <span class="ml-1 text-ink/45">Asked to see it</span>
                                    @else
                                        <form method="POST" action="{{ route('roadmap.tasks.proof.request', $task) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="ml-1 font-semibold text-club hover:underline">Show your work?</button>
                                        </form>
                                    @endif
                                @endif
                            </li>
                        @endforeach
                    </ol>
                @endif
            </div>
        @endforeach
    </div>
</details>
