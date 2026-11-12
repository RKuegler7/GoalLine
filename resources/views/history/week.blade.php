{{-- A finished GoalWeek, read-only: the result, the numbers behind it, and what got done. --}}
@php
    $result = $row['result'];
    $done = $week->tasks->whereNotNull('completed_at');
    $open = $week->tasks->whereNull('completed_at');
@endphp

<x-layouts.app :title="$week->label().' · '.$week->goal->title.' · GoalLine'">
    <x-slot:hero>
        <x-page-header :title="$week->label()" :eyebrow="$week->goal->title" :subtitle="$week->starts_on->format('M j').'–'.$week->ends_on->format('M j, Y').' · Final'"
            :back="route('dashboard', ['tab' => 'history'])" back-label="History" />
    </x-slot:hero>

    <div class="space-y-4" data-first-content data-history-week-detail>
        <section class="rounded-xl border border-line bg-white px-3 py-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                @if ($result)
                    <x-goalweek-result :result="$result" />
                @endif
                <span class="text-[13px] text-ink/70">{{ $week->planStatus() ? App\Models\RoadmapWeek::planStatusLabel($week->planStatus()) : '' }}</span>
            </div>
            <dl class="mt-3 grid grid-cols-3 gap-2 text-center">
                <div class="rounded-lg bg-mint/50 px-2 py-2">
                    <dt class="text-[11px] font-semibold text-ink/70">Of plan</dt>
                    <dd class="font-serif text-xl font-bold text-club tabular-nums">{{ $row['percent'] }}%</dd>
                </div>
                <div class="rounded-lg bg-mint/50 px-2 py-2">
                    <dt class="text-[11px] font-semibold text-ink/70">Line stars</dt>
                    <dd class="font-serif text-xl font-bold text-club tabular-nums">{{ $row['linePoints'] }}</dd>
                </div>
                <div class="rounded-lg bg-mint/50 px-2 py-2">
                    <dt class="text-[11px] font-semibold text-ink/70">Week stars</dt>
                    <dd class="font-serif text-xl font-bold text-club tabular-nums">{{ $row['weeklyPoints'] }}</dd>
                </div>
            </dl>
            @if ($match)
                <p class="mt-3 flex flex-wrap items-center justify-between gap-2 text-[13px]">
                    <span class="text-ink/80">{{ $match->league?->name }} &middot; <span class="font-semibold text-ink">{{ App\Services\History::matchLabel($match) }}</span> &middot; {{ $match->matchup_points }} matchup {{ Str::plural('star', $match->matchup_points) }}</span>
                    <a href="{{ $matchupUrl }}" class="font-semibold text-club hover:underline">See the Matchup &rarr;</a>
                </p>
            @endif
        </section>

        <section class="rounded-xl border border-line bg-white px-3 py-3">
            <h2 class="eyebrow text-club">{{ $week->focus }}</h2>
            <p class="mt-1 text-[13px] text-ink/75">Milestone: {{ $week->milestone }} @if ($week->milestone_hit_at) <span class="font-semibold text-club">&middot; hit</span> @endif</p>
            <ul class="mt-3 space-y-1.5 text-[14px]">
                @foreach ($done as $task)
                    <li class="flex gap-2"><x-icon name="check-circle" class="mt-0.5 size-4 shrink-0 text-club" /><span>{{ $task->description }}</span></li>
                @endforeach
                @foreach ($open as $task)
                    <li class="flex gap-2 text-ink/70"><span class="mt-1.5 size-2.5 shrink-0 rounded-full ring-2 ring-line ring-inset" aria-hidden="true"></span><span><span class="sr-only">Not done: </span>{{ $task->description }}</span></li>
                @endforeach
            </ul>
            <p class="mt-3 text-[13px] text-ink/70">{{ $done->count() }} of {{ $week->tasks->count() }} {{ Str::plural('task', $week->tasks->count()) }} done. Every week on the record is a week you showed up for.</p>
        </section>

        <x-scoring-help />
        <a href="{{ route('goals.show', $week->goal) }}#goalweek-{{ $week->position }}" class="inline-flex min-h-11 items-center font-semibold text-club hover:underline">Open the whole plan &rarr;</a>
    </div>
</x-layouts.app>
