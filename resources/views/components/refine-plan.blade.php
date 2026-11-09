{{-- "Refine your plan": tell the coach what to change in your own words. The change comes back as a preview first. --}}
@props(['goal', 'editableWeeks'])

@php
    $ideas = [
        'I can only train 4 days a week',
        'Add a long run on Sundays',
        'Make weeks lighter, I have finals',
        'Focus more on my weakest section',
        'Move my deadline up a week',
    ];
    $open = $goal->refinements->first(fn ($refinement) => $refinement->isOpen());
    $refineErrors = $errors->getBag('refine');
@endphp

{{-- Folded to one line until you want it; open while a refinement is in progress or a field needs fixing. --}}
<details id="refine" class="group mt-2 scroll-mt-20 rounded-xl border border-line bg-white" @if ($open || $errors->any()) open @endif>
    <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-2 px-3 [&::-webkit-details-marker]:hidden">
        <h2 class="eyebrow text-club">Refine your plan</h2>
        <x-icon name="chevron-down" class="size-4 shrink-0 text-ink/70 transition group-open:rotate-180" />
    </summary>
    <div class="border-t border-line px-3 py-2">

    @if ($open)
        <a href="{{ route('goals.refinements.show', [$goal, $open]) }}" class="mt-3 flex items-center justify-between gap-3 rounded-xl bg-mint px-4 py-3 text-sm font-semibold text-club hover:bg-mint/70">
            <span>{{ $open->status === App\Models\PlanRefinement::PENDING ? 'Your coach is working on your change…' : 'A change is ready to preview.' }}</span>
            <span>Open &rarr;</span>
        </a>
    @elseif ($editableWeeks->isEmpty())
        <p class="mt-2 text-sm text-ink/65">Every GoalWeek left is underway, and a week's plan locks when it starts, so there's nothing left to refine. Past check-ins never change.</p>
    @else
        <p class="mt-1 text-sm text-ink/65">Tell GoalLine what to change: your schedule, what to focus on, ideas and specifics. You'll see every change before anything is saved.</p>
        <form method="POST" action="{{ route('goals.refinements.store', $goal) }}" class="mt-3" data-refine>
            @csrf
            <label for="refine-instruction" class="sr-only">What should change?</label>
            <textarea id="refine-instruction" name="instruction" rows="3" maxlength="{{ App\Models\PlanRefinement::MAX_INSTRUCTION }}" required
                placeholder="For example: I travel the week of the 16th, so make that week lighter and move the work around it."
                class="block w-full rounded-xl border border-line bg-white px-3.5 py-2.5 text-ink outline-none placeholder:text-ink/40 focus:border-club focus:ring-4 focus:ring-club/10">{{ old('instruction') }}</textarea>
            <div class="mt-2 flex flex-wrap gap-2">
                @foreach ($ideas as $idea)
                    <button type="button" data-refine-idea="{{ $idea }}" class="rounded-full border border-line px-3 py-1 text-xs font-medium text-ink/70 hover:border-club/40 hover:text-club">{{ $idea }}</button>
                @endforeach
            </div>
            @if ($refineErrors->any())
                <p class="mt-2 text-sm text-red-700">{{ $refineErrors->first() }}</p>
            @endif
            <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                <p class="text-xs text-ink/70">
                    Changes {{ $editableWeeks->count() }} upcoming {{ Str::plural('GoalWeek', $editableWeeks->count()) }} at most. Past check-ins never change.
                </p>
                <button type="submit" class="rounded-full bg-club px-5 py-2 text-sm font-semibold text-ivory hover:bg-club-dark">Preview the change</button>
            </div>
        </form>
    @endif
    </div>
</details>
