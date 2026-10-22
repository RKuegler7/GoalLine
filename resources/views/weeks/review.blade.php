@php
    $goal = $week->goal;
    $result = $week->result();
    $planned = $week->tasks->reject->is_bonus;
    $habits = $week->habits();
    $writing = $review?->coach_status === App\RoadmapStatus::Pending;
@endphp

@if ($writing)
    @push('head')
        <meta http-equiv="refresh" content="6">
    @endpush
@endif

<x-layouts.app :title="'Review '.$week->label().' · GoalLine'">
    <x-slot:hero>
        <div class="mx-auto max-w-5xl px-4 pt-6 pb-12">
            <a href="{{ route('goals.show', $goal) }}#goalweek-{{ $week->position }}" class="text-sm font-medium text-ivory/75 hover:text-ivory">&larr; {{ $goal->title }}</a>
            <p class="eyebrow mt-6 text-mint">{{ $week->label() }} review &middot; {{ $week->starts_on->format('M j') }}&ndash;{{ $week->ends_on->format('M j') }}</p>
            <h1 class="mt-3 font-serif text-4xl font-medium tracking-tight text-ivory sm:text-5xl">How did it go?</h1>
            <p class="mt-3 max-w-xl text-ivory/85">Two minutes to look back, and your coach turns it into a tip for the week ahead.</p>
        </div>
    </x-slot:hero>

    <div class="mx-auto max-w-2xl space-y-6">
        {{-- The week in numbers. --}}
        <section class="rounded-2xl border border-line bg-white px-6 py-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="font-serif text-xl font-medium text-club">{{ $week->focus }}</h2>
                @if ($result)
                    <x-goalweek-result :result="$result" :close-call="$week->isCloseCall()" />
                @endif
            </div>
            <dl class="mt-4 grid grid-cols-3 gap-3 text-center">
                <div class="rounded-xl bg-ivory px-3 py-3">
                    <dt class="text-xs text-ink/55">Tasks</dt>
                    <dd class="font-serif text-2xl text-ink tabular-nums">{{ $planned->filter->completed_at->count() }}/{{ $planned->count() }}</dd>
                </div>
                <div class="rounded-xl bg-ivory px-3 py-3">
                    <dt class="text-xs text-ink/55">Habit check-ins</dt>
                    <dd class="font-serif text-2xl text-ink tabular-nums">{{ $habits->sum(fn ($habit) => $habit->doneIn($week)) }}/{{ $habits->sum(fn ($habit) => $habit->targetFor($week)) }}</dd>
                </div>
                <div class="rounded-xl bg-ivory px-3 py-3">
                    <dt class="text-xs text-ink/55">Milestone</dt>
                    <dd class="font-serif text-2xl text-ink">{{ $week->milestone_hit_at ? 'Hit' : 'Not yet' }}</dd>
                </div>
            </dl>
            @if ($week->isCheckable())
                <p class="mt-3 text-sm text-ink/55">Check-ins stay open until {{ $week->ends_on->copy()->addDays(App\Models\RoadmapWeek::CHECK_IN_GRACE_DAYS)->format('l') }} night. <a href="{{ route('goals.show', $goal) }}#goalweek-{{ $week->position }}" class="font-semibold text-club hover:underline">Catch up</a></p>
            @endif
        </section>

        @if ($review && ! request()->boolean('edit'))
            {{-- The coach's tip. --}}
            <section class="overflow-hidden rounded-2xl border border-club/30 bg-white">
                <div class="relative bg-club px-6 py-5">
                    <x-field-backdrop class="absolute inset-0 size-full opacity-70" />
                    <p class="eyebrow relative text-mint">Coach&rsquo;s tip for the week ahead</p>
                    @if ($review->coach_tip)
                        <p class="relative mt-2 font-serif text-xl leading-relaxed text-ivory">{{ $review->coach_tip }}</p>
                    @elseif ($writing)
                        <p class="relative mt-2 text-ivory/85">Your coach is reading your week and writing your tip&hellip;</p>
                    @else
                        <p class="relative mt-2 text-ivory/85">{{ app(App\Services\AiBudget::class)->blockedReason(auth()->user()) ?? 'The coach couldn’t write a tip this time.' }}</p>
                        <form method="POST" action="{{ route('goalweeks.review.store', $week) }}" class="relative mt-3">
                            @csrf
                            <input type="hidden" name="retry" value="1">
                            <button type="submit" class="rounded-full bg-ivory px-4 py-1.5 text-sm font-semibold text-club hover:bg-white">Try again</button>
                        </form>
                    @endif
                </div>
                <x-goal-line />
                <dl class="space-y-3 px-6 py-5 text-sm">
                    @if ($review->feelingLabel())
                        <div><dt class="font-semibold text-ink">How it felt</dt><dd class="text-ink/75">{{ $review->feelingLabel() }}</dd></div>
                    @endif
                    @if ($review->went_well)
                        <div><dt class="font-semibold text-ink">What went well</dt><dd class="whitespace-pre-line text-ink/75">{{ $review->went_well }}</dd></div>
                    @endif
                    @if ($review->in_the_way)
                        <div><dt class="font-semibold text-ink">What got in the way</dt><dd class="whitespace-pre-line text-ink/75">{{ $review->in_the_way }}</dd></div>
                    @endif
                </dl>
                @if ($week->isReviewable())
                    <p class="border-t border-line px-6 py-3 text-sm"><a href="{{ route('goalweeks.review', [$week, 'edit' => 1]) }}" class="font-semibold text-club hover:underline">Edit your reflection</a></p>
                @endif
            </section>
        @else
            <form method="POST" action="{{ route('goalweeks.review.store', $week) }}" class="space-y-5 rounded-2xl border border-line bg-white p-6 sm:p-8">
                @csrf
                <fieldset>
                    <legend class="font-semibold text-ink">How did this GoalWeek feel?</legend>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach (App\Models\WeekReview::FEELINGS as $value => $label)
                            <label class="cursor-pointer">
                                <input type="radio" name="feeling" value="{{ $value }}" class="peer sr-only" @checked((int) old('feeling', $review?->feeling) === $value)>
                                <span class="block rounded-full border border-line px-4 py-1.5 text-sm font-semibold text-ink/65 transition peer-checked:border-club peer-checked:bg-club peer-checked:text-ivory peer-focus-visible:ring-4 peer-focus-visible:ring-club/20">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
                <div>
                    <label for="went_well" class="font-semibold text-ink">What went well?</label>
                    <textarea id="went_well" name="went_well" rows="3" maxlength="{{ App\Models\WeekReview::MAX_LENGTH }}" placeholder="Morning study blocks worked. Hit every run."
                        class="mt-1.5 block w-full rounded-xl border border-line bg-ivory/60 px-3.5 py-2.5 text-ink outline-none placeholder:text-ink/35 focus:border-club focus:bg-white focus:ring-4 focus:ring-club/10">{{ old('went_well', $review?->went_well) }}</textarea>
                </div>
                <div>
                    <label for="in_the_way" class="font-semibold text-ink">What got in the way?</label>
                    <textarea id="in_the_way" name="in_the_way" rows="3" maxlength="{{ App\Models\WeekReview::MAX_LENGTH }}" placeholder="Late nights at work killed my evening sessions."
                        class="mt-1.5 block w-full rounded-xl border border-line bg-ivory/60 px-3.5 py-2.5 text-ink outline-none placeholder:text-ink/35 focus:border-club focus:bg-white focus:ring-4 focus:ring-club/10">{{ old('in_the_way', $review?->in_the_way) }}</textarea>
                </div>
                <x-form.button class="sm:w-auto sm:px-8">Get my coach&rsquo;s tip</x-form.button>
            </form>
        @endif
    </div>
</x-layouts.app>
