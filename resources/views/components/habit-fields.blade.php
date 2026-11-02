{{-- A habit's name, its cadence (every day, specific days, or X times a week), and its rule for what counts. --}}
@props(['habit' => null, 'prefix'])

@php
    $cadence = old('cadence', $habit?->cadence ?? App\Models\Habit::WEEKLY);
    $times = (int) old('times_per_week', $habit?->times_per_week && $habit->times_per_week < 7 ? $habit->times_per_week : 3);
    $days = array_map('intval', old('days', $habit?->days ?? []));
    $rule = old('rule', $habit?->rule ?? App\Models\Habit::CHECK);
    $chip = 'block rounded-full border border-line px-3 py-1.5 text-xs font-semibold text-ink/60 transition peer-checked:border-club peer-checked:bg-club peer-checked:text-ivory peer-focus-visible:ring-4 peer-focus-visible:ring-club/20';
    $input = 'rounded-lg border border-line bg-white px-3 py-2 text-sm outline-none placeholder:text-ink/35 focus:border-club focus:ring-4 focus:ring-club/10';
@endphp

<div data-habit-fields>
    <label for="{{ $prefix }}-title" class="text-sm font-semibold text-ink">Habit</label>
    <input id="{{ $prefix }}-title" name="title" value="{{ old('title', $habit?->title) }}" maxlength="80" required placeholder="Run"
        class="mt-1 w-full {{ $input }}">

    <fieldset class="mt-4">
        <legend class="text-sm font-semibold text-ink">When</legend>
        <div class="mt-1 flex flex-wrap gap-1.5">
            @foreach ([App\Models\Habit::DAILY => 'Every day', App\Models\Habit::DAYS => 'Specific days', App\Models\Habit::WEEKLY => 'X times a week'] as $value => $label)
                <label class="cursor-pointer">
                    <input type="radio" name="cadence" value="{{ $value }}" class="peer sr-only" @checked($cadence === $value)>
                    <span class="{{ $chip }}">{{ $label }}</span>
                </label>
            @endforeach
        </div>

        <div class="mt-2 flex flex-wrap gap-1.5" data-show-for="cadence:{{ App\Models\Habit::DAYS }}">
            @foreach (App\Models\Habit::DAY_NAMES as $number => $name)
                <label class="cursor-pointer">
                    <input type="checkbox" name="days[]" value="{{ $number }}" class="peer sr-only" @checked(in_array($number, $days, true))>
                    <span class="{{ $chip }}">{{ $name }}</span>
                </label>
            @endforeach
        </div>

        <div class="mt-2" data-show-for="cadence:{{ App\Models\Habit::WEEKLY }}">
            <label for="{{ $prefix }}-times" class="sr-only">Times a week</label>
            <select id="{{ $prefix }}-times" name="times_per_week" class="{{ $input }}">
                @foreach (range(1, 6) as $count)
                    <option value="{{ $count }}" @selected($count === $times)>{{ $count }}&times; a week, any days</option>
                @endforeach
            </select>
            <p class="mt-1 text-xs text-ink/55">Progress counts toward the target, like &ldquo;3 of 4 this week&rdquo;. A day off isn't a miss.</p>
        </div>
    </fieldset>

    <fieldset class="mt-4">
        <legend class="text-sm font-semibold text-ink">What counts</legend>
        <div class="mt-1 flex flex-wrap gap-1.5">
            @foreach ([App\Models\Habit::CHECK => 'Did it or not', App\Models\Habit::NUMBER => 'A number', App\Models\Habit::MINUTES => 'Minutes'] as $value => $label)
                <label class="cursor-pointer">
                    <input type="radio" name="rule" value="{{ $value }}" class="peer sr-only" @checked($rule === $value)>
                    <span class="{{ $chip }}">{{ $label }}</span>
                </label>
            @endforeach
        </div>
        <div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-ink/70" data-show-for="rule:{{ App\Models\Habit::NUMBER }},{{ App\Models\Habit::MINUTES }}">
            <span>At least</span>
            <label for="{{ $prefix }}-threshold" class="sr-only">At least</label>
            <input id="{{ $prefix }}-threshold" name="threshold" type="number" inputmode="decimal" step="any" min="0" value="{{ old('threshold', $habit?->threshold ? $habit->formatNumber($habit->threshold) : null) }}" placeholder="2"
                class="w-20 tabular-nums {{ $input }}">
            <span data-show-for="rule:{{ App\Models\Habit::MINUTES }}">minutes</span>
            <span data-show-for="rule:{{ App\Models\Habit::NUMBER }}">
                <label for="{{ $prefix }}-unit" class="sr-only">Unit</label>
                <input id="{{ $prefix }}-unit" name="unit" maxlength="20" value="{{ old('unit', $habit?->unit) }}" placeholder="miles" class="w-28 {{ $input }}">
            </span>
        </div>
        <p class="mt-1 text-xs text-ink/55">The rule shows on the habit, like &ldquo;Run &middot; 2+ miles&rdquo;, so it's clear what counts.</p>
    </fieldset>
</div>
