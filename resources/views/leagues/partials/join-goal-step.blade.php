{{-- The goal step when joining: the league goal ready to use in one tap, "Make it yours" edits, a different goal, or a
     goal you already have. Every option joins and goes straight into the intake questions and the plan. Needs $league,
     $goalAction, $joinAction and $goals. --}}
@php
    $category = $league->category;
    $chips = App\GoalExamples::chips($category);
    $min = App\Clock::today()->addDay()->format('Y-m-d');
    $seasonEnd = $league->ends_on->format('Y-m-d');
    $defaultDate = old('target_date', ($league->goal_target_date && $league->goal_target_date->gte(App\Clock::today()->addDay()) ? $league->goal_target_date : $league->ends_on)->format('Y-m-d'));
    $field = 'block w-full rounded-lg border border-line bg-white px-3.5 py-2.5 text-ink outline-none focus:border-club focus:ring-4 focus:ring-club/10';
    $label = 'mb-1.5 block text-sm font-medium text-ink/80';
    $customFirst = ! $league->hasGoal();
@endphp

@if ($errors->any())
    <div class="mb-4 rounded-xl bg-red-50 px-3 py-2 text-sm text-red-800" role="alert">
        @foreach ($errors->all() as $message)
            <p>{{ $message }}</p>
        @endforeach
    </div>
@endif

@if ($league->hasGoal())
    {{-- The league goal, ready to use. --}}
    <form method="POST" action="{{ $goalAction }}" class="overflow-hidden rounded-2xl border-2 border-club" data-join-goal="league">
        @csrf
        <input type="hidden" name="title" value="{{ $league->goal_title }}">
        <input type="hidden" name="category" value="{{ $category?->value ?? App\GoalCategory::Other->value }}">
        <div class="gl-field px-4 py-4 text-ivory">
            <p class="eyebrow text-[11px] text-mint">Same goal as the league</p>
            <p class="mt-1 text-[22px] leading-tight font-extrabold">{{ $league->goalSummary() }}</p>
            @if ($league->goal_description)
                <p class="mt-1 text-[14px] text-ivory/90">{{ $league->goal_description }}</p>
            @endif
            @if ($league->goal_target_result)
                <p class="mt-1 text-[13px] text-ivory/90">Target: {{ $league->goal_target_result }}</p>
            @endif
        </div>
        <div class="px-4 py-3">
            <button type="submit" class="inline-flex min-h-12 w-full items-center justify-center rounded-full bg-club px-6 text-[15px] font-bold text-ivory hover:bg-club-dark sm:w-auto">Use this goal</button>
            <p class="mt-2 text-xs text-ink/70">Next: a few questions about where you're starting from, then your own plan to look over and accept.</p>

            <details class="group mt-3 border-t border-line pt-3" @if ($errors->hasAny(['target_date', 'target_result', 'description', 'why', 'chips'])) open @endif>
                <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between text-sm font-semibold text-club [&::-webkit-details-marker]:hidden">
                    Make it yours <x-icon name="chevron-down" class="size-4 transition group-open:rotate-180" />
                </summary>
                <div class="mt-2 space-y-3">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label for="join-date" class="{{ $label }}">Done by</label>
                            <input id="join-date" name="target_date" type="date" value="{{ $defaultDate }}" min="{{ $min }}" required class="{{ $field }}" aria-describedby="join-date-hint">
                            <p id="join-date-hint" class="mt-1 text-xs text-ink/70">The season ends {{ $league->ends_on->format('M j, Y') }}.</p>
                        </div>
                        <div>
                            <label for="join-result" class="{{ $label }}">Target result <span class="font-normal text-ink/70">(optional)</span></label>
                            <input id="join-result" name="target_result" maxlength="80" value="{{ old('target_result', $league->goal_target_result) }}" placeholder="{{ App\GoalExamples::result($category) }}" class="{{ $field }}">
                        </div>
                    </div>
                    <label class="flex items-start gap-2 rounded-lg bg-ivory px-3 py-2 text-[13px] text-ink/80">
                        <input type="checkbox" name="past_season" value="1" class="mt-0.5 accent-club" @checked(old('past_season'))>
                        <span>Keep my later date. If my date is after {{ $league->ends_on->format('M j') }}, my matchups end with the season and I keep going against the line after it.</span>
                    </label>
                    <div>
                        <label for="join-description" class="{{ $label }}">A short description <span class="font-normal text-ink/70">(optional)</span></label>
                        <input id="join-description" name="description" maxlength="280" value="{{ old('description', $league->goal_description) }}" class="{{ $field }}">
                    </div>
                    <fieldset>
                        <legend class="{{ $label }}">Tell the coach a little more <span class="font-normal text-ink/70">(optional)</span></legend>
                        <div class="flex flex-wrap gap-2" data-chips>
                            @foreach ($chips as $chip => $sentence)
                                <label class="inline-flex min-h-10 cursor-pointer items-center rounded-full border border-club/30 px-3 text-[13px] font-semibold text-club has-[:checked]:border-club has-[:checked]:bg-club has-[:checked]:text-ivory has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-club">
                                    <input type="checkbox" name="chips[]" value="{{ $chip }}" class="sr-only" @checked(in_array($chip, old('chips', []), true))> {{ $chip }}
                                </label>
                            @endforeach
                        </div>
                        <p class="mt-1 text-xs text-ink/70">These shape your questions and plan. Plans are general guidance, not medical advice.</p>
                    </fieldset>
                    <div>
                        <label for="join-why" class="{{ $label }}">Why this goal matters <span class="font-normal text-ink/70">(optional, only you see it)</span></label>
                        <textarea id="join-why" name="why" rows="2" maxlength="1000" class="{{ $field }}">{{ old('why') }}</textarea>
                    </div>
                    <button type="submit" class="inline-flex min-h-11 items-center rounded-full border border-club px-5 text-sm font-semibold text-club hover:bg-mint">Use my version</button>
                </div>
            </details>
        </div>
    </form>
@endif

{{-- A different goal: each player is scored against their own plan, so mixed goals work. --}}
<details class="group mt-4 rounded-xl border border-line bg-white" @if ($customFirst || $errors->has('title')) open @endif data-join-goal="custom">
    <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between px-3 text-sm font-semibold text-club [&::-webkit-details-marker]:hidden">
        {{ $customFirst ? 'Set the goal you\'ll play' : 'Choose a different goal' }} <x-icon name="chevron-down" class="size-4 transition group-open:rotate-180" />
    </summary>
    <form method="POST" action="{{ $goalAction }}" class="space-y-3 border-t border-line px-3 py-3">
        @csrf
        <p class="text-xs text-ink/70">Everyone is scored against their own plan, so different goals play fine in the same league.</p>
        <div>
            <label for="custom-title" class="{{ $label }}">Your goal</label>
            <input id="custom-title" name="title" maxlength="120" required value="{{ $customFirst ? old('title') : '' }}" placeholder="{{ App\GoalExamples::title($category) }}" class="{{ $field }}">
        </div>
        <div class="grid gap-3 sm:grid-cols-2">
            <div>
                <label for="custom-category" class="{{ $label }}">Kind of goal</label>
                <select id="custom-category" name="category" required class="{{ $field }}">
                    @foreach (App\GoalCategory::cases() as $option)
                        <option value="{{ $option->value }}" @selected(old('category', $category?->value) === $option->value)>{{ $option->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="custom-date" class="{{ $label }}">Done by</label>
                <input id="custom-date" name="target_date" type="date" value="{{ $seasonEnd }}" min="{{ $min }}" required class="{{ $field }}">
            </div>
        </div>
        <label class="flex items-start gap-2 text-[13px] text-ink/80">
            <input type="checkbox" name="past_season" value="1" class="mt-0.5 accent-club"> <span>Keep a date after the season ends ({{ $league->ends_on->format('M j') }}).</span>
        </label>
        <button type="submit" class="inline-flex min-h-11 items-center rounded-full bg-club px-5 text-sm font-semibold text-ivory hover:bg-club-dark">Join with this goal</button>
    </form>
</details>

{{-- A goal you already have. --}}
@if ($goals->isNotEmpty())
    <details class="group mt-3 rounded-xl border border-line bg-white" data-join-goal="existing">
        <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between px-3 text-sm font-semibold text-club [&::-webkit-details-marker]:hidden">
            Play a goal you already have <x-icon name="chevron-down" class="size-4 transition group-open:rotate-180" />
        </summary>
        <form method="POST" action="{{ $joinAction }}" class="space-y-3 border-t border-line px-3 py-3">
            @csrf
            <label for="goal_id" class="sr-only">Your goal</label>
            <select id="goal_id" name="goal_id" required class="{{ $field }}">
                @foreach ($goals as $goal)
                    <option value="{{ $goal->id }}" @selected((int) old('goal_id', request()->integer('goal')) === $goal->id)>{{ $goal->title }} (by {{ $goal->target_date->format('M j, Y') }})</option>
                @endforeach
            </select>
            <button type="submit" class="inline-flex min-h-11 items-center rounded-full border border-club px-5 text-sm font-semibold text-club hover:bg-mint">Join with this goal</button>
        </form>
    </details>
@endif
