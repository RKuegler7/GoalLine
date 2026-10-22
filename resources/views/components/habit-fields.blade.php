{{-- Name, frequency, and optional preferred days for a habit. --}}
@props(['habit' => null, 'prefix'])

@php
    $times = (int) old('times_per_week', $habit?->times_per_week ?? 3);
    $days = array_map('intval', old('days', $habit?->days ?? []));
    $names = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];
@endphp

<div class="grid gap-3 sm:grid-cols-[1fr_auto]">
    <div>
        <label for="{{ $prefix }}-title" class="text-sm font-semibold text-ink">Habit</label>
        <input id="{{ $prefix }}-title" name="title" value="{{ old('title', $habit?->title) }}" maxlength="80" required placeholder="Run 3 miles"
            class="mt-1 w-full rounded-lg border border-line bg-white px-3 py-2 text-sm outline-none placeholder:text-ink/35 focus:border-club focus:ring-4 focus:ring-club/10">
    </div>
    <div>
        <label for="{{ $prefix }}-times" class="text-sm font-semibold text-ink">How often</label>
        <select id="{{ $prefix }}-times" name="times_per_week"
            class="mt-1 w-full rounded-lg border border-line bg-white px-3 py-2 text-sm outline-none focus:border-club focus:ring-4 focus:ring-club/10">
            @foreach (range(1, 7) as $count)
                <option value="{{ $count }}" @selected($count === $times)>{{ $count === 7 ? 'Every day' : $count.'× a week' }}</option>
            @endforeach
        </select>
    </div>
</div>
<fieldset class="mt-3">
    <legend class="text-sm font-semibold text-ink">Preferred days <span class="font-normal text-ink/50">(optional)</span></legend>
    <div class="mt-1 flex flex-wrap gap-1.5">
        @foreach ($names as $number => $name)
            <label class="cursor-pointer">
                <input type="checkbox" name="days[]" value="{{ $number }}" class="peer sr-only" @checked(in_array($number, $days, true))>
                <span class="block rounded-full border border-line px-3 py-1 text-xs font-semibold text-ink/60 transition peer-checked:border-club peer-checked:bg-club peer-checked:text-ivory peer-focus-visible:ring-4 peer-focus-visible:ring-club/20">{{ $name }}</span>
            </label>
        @endforeach
    </div>
</fieldset>
