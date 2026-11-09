{{-- One task on the matchup pager, in one compact line: status, title, points, Verified, group or bonus, and a proof
     thumbnail if the viewer may see it. Tapping it opens the task sheet (or its own page without JavaScript). --}}
@props(['row'])

@php
    $task = $row['task'];
    $tone = match ($row['status']) {
        App\Services\MatchupPager::STATUS_DONE => 'bg-grass text-ink',
        App\Services\MatchupPager::STATUS_TODAY => 'bg-gold/30 text-ink',
        App\Services\MatchupPager::STATUS_MISSED => 'bg-ink/10 text-ink/70',
        default => 'bg-ivory text-ink/70 ring-1 ring-inset ring-line',
    };
@endphp

<li>
    <a href="{{ $row['sheetUrl'] }}" data-task-sheet-open title="{{ $task->description }} · {{ $row['statusLabel'] }}"
        class="flex min-h-11 items-center gap-1.5 rounded-lg px-1 py-1 hover:bg-mint/40 focus-visible:bg-mint/40">
        <span @class(['grid size-5 shrink-0 place-items-center rounded-full text-[11px] font-bold', $tone]) aria-hidden="true">
            @switch($row['status'])
                @case(App\Services\MatchupPager::STATUS_DONE) &check; @break
                @case(App\Services\MatchupPager::STATUS_MISSED) &ndash; @break
                @case(App\Services\MatchupPager::STATUS_TODAY) &bull; @break
                @default &middot;
            @endswitch
        </span>
        <span class="min-w-0 flex-1">
            <span class="block truncate text-[13px] leading-tight text-ink">{{ $task->description }}</span>
            <span class="flex items-center gap-1 text-[11px] leading-tight text-ink/70">
                <span class="sr-only">{{ $row['statusLabel'] }} &middot;</span>
                <span class="tabular-nums">{{ $row['points'] }} {{ Str::plural('pt', $row['points']) }}</span>
                @if ($row['result'])
                    <span class="truncate tabular-nums">&middot; {{ $row['result'] }}</span>
                @endif
                @if ($row['bonus'])
                    <span class="rounded-full bg-gold/25 px-1 font-semibold text-ink">Bonus</span>
                @endif
                @if ($row['group'])
                    <span class="rounded-full bg-club/10 px-1 font-semibold text-club" title="Group task voted in by the league">Group</span>
                @endif
                @if ($row['verified'])
                    <span class="inline-flex items-center gap-0.5 font-semibold text-club" title="Verified"><x-icon name="check-circle" class="size-3" /><span class="sr-only">Verified</span></span>
                @endif
            </span>
        </span>
        @if ($row['proofUrl'])
            <img src="{{ $row['proofUrl'] }}" alt="Proof for {{ $task->description }}" loading="lazy" decoding="async" width="28" height="28" class="size-7 shrink-0 rounded-md object-cover ring-1 ring-line">
        @endif
    </a>
</li>
