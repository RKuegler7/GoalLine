{{-- Monday to Sunday: check-ins per day, habit-tracker style. --}}
@props(['days'])

@php
    $total = collect($days)->sum('count');
    $activeDays = collect($days)->where('count', '>', 0)->count();
@endphp

<section class="rounded-2xl border border-line bg-white px-5 py-4">
    <div class="flex items-baseline justify-between gap-4">
        <h2 class="eyebrow text-club">Week at a glance <a href="{{ route('recap.week') }}" class="ml-2 font-sans text-xs font-semibold tracking-normal normal-case text-club hover:underline">Your week &rarr;</a></h2>
        <span class="text-sm text-ink/55">{{ $total }} {{ Str::plural('check-in', $total) }} &middot; {{ $activeDays }} of 7 days</span>
    </div>
    <ol class="mt-4 grid grid-cols-7 gap-2">
        @foreach ($days as $day)
            <li class="flex flex-col items-center gap-1.5">
                <span @class(['text-xs font-semibold', 'text-club' => $day['isToday'], 'text-ink/50' => ! $day['isToday']])>{{ $day['date']->format('D') }}</span>
                <span @class([
                    'flex aspect-square w-full max-w-12 items-center justify-center rounded-xl text-sm font-semibold tabular-nums',
                    'bg-grass text-white' => $day['count'] >= 3,
                    'bg-grass/60 text-white' => $day['count'] > 0 && $day['count'] < 3,
                    'bg-ivory text-ink/30' => $day['count'] === 0 && ! $day['isFuture'],
                    'border border-dashed border-line text-ink/20' => $day['isFuture'],
                    'ring-2 ring-club ring-offset-2' => $day['isToday'],
                ]) title="{{ $day['date']->format('l') }}: {{ $day['count'] }} {{ Str::plural('check-in', $day['count']) }}">
                    {{ $day['isFuture'] ? '' : $day['count'] }}
                </span>
            </li>
        @endforeach
    </ol>
</section>
