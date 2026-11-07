{{-- Disputes on one check-in: the "Disputed" label, and the actions each person has. The matchup opponent or league
     owner can dispute; the owner of the check-in settles it with proof or an explanation; the disputer can withdraw;
     the league owner can rule. Calm language: it's a question about the process, not an accusation. --}}
@props(['task', 'league', 'owner', 'canDispute' => false])

@php
    $viewer = auth()->user();
    $open = $task->disputes->where('status', App\Models\Dispute::OPEN);
    $mine = $open->firstWhere('disputer_id', $viewer->id);
    $isLeagueOwner = $league->commissioner_id === $viewer->id;
    $isOwner = $owner->is($viewer);
@endphp

@if ($task->dispute_status === App\Models\RoadmapTask::DISPUTE_UPHELD)
    <span class="ml-1 rounded-full bg-ink/10 px-1.5 font-semibold text-ink/70">Ruled out by the league owner</span>
@elseif ($open->isNotEmpty())
    <span class="ml-1 rounded-full bg-gold/20 px-1.5 font-semibold text-ink">Disputed</span>
    @if ($isOwner || $mine || $isLeagueOwner)
        <div class="mt-1 space-y-1.5 rounded-lg bg-ivory px-2.5 py-2">
            @foreach ($open as $dispute)
                <p class="text-ink/70">
                    {{ $dispute->disputer->is($viewer) ? 'You' : Str::before($dispute->disputer->name, ' ') }} asked about this
                    @if ($dispute->reason)
                        : &ldquo;{{ $dispute->reason }}&rdquo;
                    @endif
                </p>
                @if ($isOwner)
                    <form method="POST" action="{{ route('disputes.explain', $dispute) }}" data-live class="flex gap-1.5">
                        @csrf
                        <label for="explain-{{ $dispute->id }}" class="sr-only">Explain</label>
                        <input id="explain-{{ $dispute->id }}" name="note" maxlength="{{ App\Models\Dispute::MAX_NOTE }}" required placeholder="Where and when you did it"
                            class="min-w-0 flex-1 rounded-lg border border-line bg-white px-2 py-1 text-xs outline-none focus:border-club">
                        <button type="submit" class="rounded-lg bg-club px-2.5 py-1 text-xs font-semibold text-ivory">Explain</button>
                    </form>
                    <a href="{{ route('goals.show', $task->week->goal) }}#goalweek-{{ $task->week->position }}" class="font-semibold text-club hover:underline">Or add a photo</a>
                @endif
                @if ($dispute->disputer_id === $viewer->id)
                    <form method="POST" action="{{ route('disputes.withdraw', $dispute) }}" data-live class="inline">
                        @csrf
                        <button type="submit" class="font-semibold text-club hover:underline">Withdraw</button>
                    </form>
                @endif
                @if ($isLeagueOwner && ! $isOwner && $dispute->league_id === $league->id)
                    <span class="flex flex-wrap items-center gap-1.5">
                        <span class="text-ink/55">League owner:</span>
                        @foreach (['counts' => 'It counts', 'out' => 'It doesn\'t count'] as $outcome => $label)
                            <form method="POST" action="{{ route('disputes.rule', $dispute) }}" data-live class="inline">
                                @csrf
                                <input type="hidden" name="outcome" value="{{ $outcome }}">
                                <button type="submit" class="rounded-full border border-line px-2 py-0.5 font-semibold text-ink/70 hover:border-club/40">{{ $label }}</button>
                            </form>
                        @endforeach
                    </span>
                @endif
            @endforeach
        </div>
    @endif
@elseif ($task->disputes->isNotEmpty())
    <span class="ml-1 text-ink/45">Dispute settled</span>
@endif

@if ($canDispute && ! $mine && $task->dispute_status !== App\Models\RoadmapTask::DISPUTE_UPHELD)
    <details class="ml-1 inline-block">
        <summary class="cursor-pointer list-none font-semibold text-ink/50 hover:text-club [&::-webkit-details-marker]:hidden">Dispute</summary>
        <form method="POST" action="{{ route('disputes.store', $task) }}" data-live class="mt-1 flex gap-1.5">
            @csrf
            <label for="dispute-{{ $task->id }}" class="sr-only">Why? (optional)</label>
            <input id="dispute-{{ $task->id }}" name="reason" maxlength="{{ App\Models\Dispute::MAX_REASON }}" placeholder="Why? (optional)"
                class="min-w-0 flex-1 rounded-lg border border-line bg-white px-2 py-1 text-xs outline-none focus:border-club">
            <button type="submit" class="rounded-lg border border-line px-2.5 py-1 text-xs font-semibold text-ink/70 hover:border-club/40">Dispute</button>
        </form>
    </details>
@endif
