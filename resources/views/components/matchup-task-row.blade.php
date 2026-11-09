{{-- One task on the matchup pager: title, points, status, its number, Verified, group task, and the proof if the
     viewer may see it. Tapping it opens the task sheet (or its own page without JavaScript). --}}
@props(['row'])

@php
    $task = $row['task'];
    $tone = match ($row['status']) {
        App\Services\MatchupPager::STATUS_DONE => 'bg-grass text-white',
        App\Services\MatchupPager::STATUS_TODAY => 'bg-gold/30 text-ink',
        App\Services\MatchupPager::STATUS_MISSED => 'bg-red-50 text-red-700',
        default => 'bg-ivory text-ink/55 ring-1 ring-inset ring-line',
    };
@endphp

<li>
    <a href="{{ $row['sheetUrl'] }}" data-task-sheet-open class="flex min-h-11 items-center gap-3 rounded-xl px-2 py-1.5 hover:bg-mint/40 focus-visible:bg-mint/40">
        <span @class(['grid size-7 shrink-0 place-items-center rounded-full text-xs font-bold', $tone]) aria-hidden="true">
            @switch($row['status'])
                @case(App\Services\MatchupPager::STATUS_DONE) &check; @break
                @case(App\Services\MatchupPager::STATUS_MISSED) &times; @break
                @case(App\Services\MatchupPager::STATUS_TODAY) &bull; @break
                @default &middot;
            @endswitch
        </span>
        <span class="min-w-0 flex-1">
            <span class="block truncate text-sm font-medium text-ink">{{ $task->description }}</span>
            <span class="flex flex-wrap items-center gap-x-1.5 text-xs text-ink/55">
                <span>{{ $row['statusLabel'] }}</span>
                <span aria-hidden="true">&middot;</span>
                <span class="tabular-nums">{{ $row['points'] }} {{ Str::plural('pt', $row['points']) }}</span>
                @if ($row['result'])
                    <span aria-hidden="true">&middot;</span>
                    <span class="tabular-nums">{{ $row['result'] }}</span>
                @endif
                @if ($row['bonus'])
                    <span class="rounded-full bg-gold/25 px-1.5 text-[0.6rem] font-semibold text-ink">Bonus</span>
                @endif
                @if ($row['group'])
                    <span class="inline-flex items-center gap-0.5 rounded-full bg-club/10 px-1.5 text-[0.6rem] font-semibold text-club" title="Group task voted in by the league">
                        <svg viewBox="0 0 20 20" class="size-3" fill="currentColor" aria-hidden="true"><path d="M7 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm6 0a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5ZM1 16c0-3 2.7-5 6-5s6 2 6 5v1H1v-1Zm13 1v-1c0-1.7-.6-3.1-1.6-4.2.5-.1 1-.1 1.6-.1 2.8 0 5 1.7 5 4.3v1h-5Z" /></svg>
                        Group
                    </span>
                @endif
                @if ($row['verified'])
                    <span class="font-semibold text-club">&check; Verified</span>
                @endif
            </span>
        </span>
        @if ($row['proofUrl'])
            <img src="{{ $row['proofUrl'] }}" alt="Proof for {{ $task->description }}" loading="lazy" decoding="async" width="44" height="44" class="size-11 shrink-0 rounded-lg object-cover ring-1 ring-line">
        @endif
    </a>
</li>
