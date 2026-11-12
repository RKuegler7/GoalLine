{{-- The line: a field track from 0% to past Smashed, with white yard lines at In line, the plan (100%) and Smashed,
     band-coloured zones, and each player's avatar puck at their credited percent (the first above the track, the
     second below, so they never cover each other). A dashed ghost marks where the current pace projects. Tap a puck
     for its details. Positions come from MatchupLine and the scoring code; the browser only draws them. --}}
@props(['sides', 'final' => false, 'index' => 0])

@php
    $zones = App\Services\MatchupLine::zones();
    $markers = App\Services\MatchupLine::markers();
    $zoneColour = fn ($band) => match ($band) {
        App\GoalWeekResult::Short => 'bg-[#f3b4ac]',
        App\GoalWeekResult::InLine => 'bg-grass',
        App\GoalWeekResult::Past => 'bg-gradient-to-r from-gold via-[#f0dca4] to-gold',
        App\GoalWeekResult::Smashed => 'bg-sky-600',
    };
    $ringColour = fn ($band) => match ($band) {
        App\GoalWeekResult::InLine => 'ring-grass',
        App\GoalWeekResult::Past => 'ring-gold',
        App\GoalWeekResult::Smashed => 'ring-sky-500',
        default => 'ring-[#f3b4ac]',
    };
@endphp

<div class="border-t border-ivory/15 bg-club-dark/30 px-4 pt-2 pb-3" data-matchup-line>
    <ul class="sr-only">
        @foreach ($sides as $side)
            <li data-line-text>{{ App\Services\MatchupLine::describe(Str::before($side['member']->displayName(), ' ') ?: $side['member']->displayName(), $side) }}</li>
        @endforeach
    </ul>

    <div class="relative h-24" aria-hidden="true">
        {{-- Zones and the track. --}}
        <div class="absolute inset-x-0 top-1/2 flex h-2.5 -translate-y-1/2 overflow-hidden rounded-full">
            @foreach ($zones as $zone)
                <span class="{{ $zoneColour($zone['band']) }} h-full" style="width: {{ $zone['to'] - $zone['from'] }}%"></span>
            @endforeach
        </div>

        {{-- Yard lines at the thresholds, with their labels. --}}
        @foreach ($markers as $marker)
            <span class="absolute top-3 bottom-3 w-px bg-ivory/90" style="left: {{ $marker['position'] }}%" data-line-marker="{{ $marker['key'] }}" data-percent="{{ $marker['percent'] }}"></span>
            {{-- In line's label sits left of its line and the plan's right of its own, so the two close lines stay readable. --}}
            <span @class([
                'absolute bottom-0 text-[10px] font-semibold whitespace-nowrap text-ivory',
                '-translate-x-full pr-0.5' => $marker['key'] === 'in_line',
                'pl-0.5' => $marker['key'] === 'plan',
                '-translate-x-1/2' => $marker['key'] === 'smashed',
            ]) style="left: {{ $marker['position'] }}%">{{ $marker['percent'] }}{{ $marker['key'] === 'plan' ? ' plan' : '' }}</span>
        @endforeach
        <span class="absolute bottom-0 left-0 text-[10px] font-semibold text-ivory/90">0</span>

        {{-- Pucks: the first player above the track, the second below; the projection as a dashed ghost. --}}
        @foreach ($sides as $slot => $side)
            @continue($side['percent'] === null)
            @php
                $above = $slot === 0;
                $projected = $final ? null : $side['projection']['percent'];
            @endphp
            @if ($projected !== null && $projected !== $side['percent'])
                <span class="gl-puck absolute size-6 -translate-x-1/2 rounded-full border-2 border-dashed border-ivory/80 {{ $above ? 'top-0.5' : 'bottom-3.5' }}"
                    style="--x: {{ App\Services\MatchupLine::position($projected) }}%" data-line-ghost="{{ $slot }}" title="Projected on current pace: {{ $projected }}%"></span>
            @endif
            <button type="button" class="gl-puck absolute -translate-x-1/2 rounded-full {{ $above ? 'top-0' : 'bottom-3' }}"
                style="--x: {{ App\Services\MatchupLine::position($side['percent']) }}%" data-line-puck="{{ $index }}-{{ $slot }}" data-percent="{{ $side['percent'] }}"
                aria-controls="line-detail-{{ $index }}-{{ $slot }}" aria-expanded="false" tabindex="-1">
                <x-avatar :user="$side['member']->user" tone="light" class="size-7 text-[10px] ring-3 {{ $ringColour($side['result']) }}" />
            </button>
        @endforeach
    </div>

    {{-- Details for a tapped puck (also reachable by keyboard). --}}
    <div class="mt-1 flex flex-wrap gap-1.5">
        @foreach ($sides as $slot => $side)
            @continue($side['percent'] === null)
            <button type="button" data-line-toggle="{{ $index }}-{{ $slot }}" aria-controls="line-detail-{{ $index }}-{{ $slot }}" aria-expanded="false"
                class="inline-flex min-h-8 items-center gap-1 rounded-full bg-ivory/15 px-2.5 text-[11px] font-semibold text-ivory hover:bg-ivory/25">
                {{ Str::before($side['member']->displayName(), ' ') ?: $side['member']->displayName() }} on the line
            </button>
        @endforeach
    </div>
    @foreach ($sides as $slot => $side)
        @continue($side['percent'] === null)
        <div id="line-detail-{{ $index }}-{{ $slot }}" hidden data-line-detail class="mt-2 rounded-xl bg-ivory px-3 py-2 text-[13px] text-ink">
            <p>{{ App\Services\MatchupLine::describe(Str::before($side['member']->displayName(), ' ') ?: $side['member']->displayName(), $side) }}</p>
            @if (! $final && $side['projection']['band'])
                <p class="mt-0.5 text-ink/75">On current pace: {{ $side['projection']['percent'] }}%, {{ $side['projection']['band']->label() }}.</p>
            @endif
            <p class="mt-1.5 flex flex-wrap gap-3 font-semibold">
                @if ($side['member']->user->username)
                    <a href="{{ route('profiles.show', $side['member']->user) }}" class="text-club hover:underline">Profile</a>
                @endif
                <a href="{{ route('leagues.feed', ['league' => $side['member']->league_id, 'member' => $side['member']->user_id]) }}" class="text-club hover:underline">League highlights</a>
            </p>
        </div>
    @endforeach
</div>
