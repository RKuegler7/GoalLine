{{-- "How is GoalLine scored?": one compact button that opens the scoring sheet (a bottom sheet on phones, a dialog on
     desktop). Without scripts it's a link to the scoring section of About. The sheet itself renders once per page. --}}
<a href="{{ route('about') }}#scoring" data-open-sheet="scoring-sheet" {{ $attributes->class('inline-flex min-h-11 items-center gap-1.5 rounded-full px-1 text-[13px] font-semibold text-club hover:underline') }} data-scoring-help>
    <x-icon name="info" class="size-4 shrink-0" /> How is GoalLine scored?
</a>

@once
    @push('sheets')
        <x-scoring-sheet />
    @endpush
@endonce
