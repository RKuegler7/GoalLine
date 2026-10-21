{{-- A GoalWeek result pill with its points. --}}
@props(['result', 'closeCall' => false])

<span {{ $attributes->class([
    'eyebrow inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[0.65rem]',
    'bg-red-50 text-red-700' => $result === App\GoalWeekResult::Short,
    'bg-amber-100 text-amber-800' => $result === App\GoalWeekResult::InLine && $closeCall,
    'bg-mint text-club' => $result === App\GoalWeekResult::InLine && ! $closeCall,
    'bg-grass text-white' => $result === App\GoalWeekResult::Past,
    'bg-gold text-ink' => $result === App\GoalWeekResult::Smashed,
]) }}>
    {{ $result->label() }}{{ $closeCall ? ', close call' : '' }}
    <span class="opacity-70">&middot; {{ $result->points() }} {{ Str::plural('pt', $result->points()) }}</span>
</span>
