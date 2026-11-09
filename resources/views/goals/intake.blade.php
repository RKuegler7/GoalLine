@php
    $questions = $intake->questions ?? [];
    $editing = ! $intake->isOpen();
    $chip = 'flex cursor-pointer items-center gap-2 rounded-full border border-line bg-white px-4 py-2 text-sm font-medium text-ink/80 transition hover:border-club/40 has-checked:border-club has-checked:bg-mint has-checked:text-club has-focus-visible:ring-4 has-focus-visible:ring-club/10';
@endphp

<x-layouts.app :title="'A few questions · GoalLine'">
    @if ($intake->status === App\Models\GoalIntake::GENERATING)
        @push('head')
            <meta http-equiv="refresh" content="3">
        @endpush
    @endif

    <x-slot:hero>
        <div class="mx-auto max-w-5xl px-4 pt-6 pb-12">
            <p class="eyebrow text-mint">{{ $editing ? 'Your answers' : 'Goal set' }}</p>
            <h1 class="mt-3 font-serif text-4xl font-bold tracking-tight text-ivory sm:text-5xl">{{ $editing ? 'Update your answers' : 'A few questions first' }}</h1>
            <p class="mt-3 max-w-xl text-ivory/85">
                @if ($editing)
                    Change anything that's different now. Your GoalWeeks that haven't started will be re-planned around your answers.
                @else
                    Like a good coach, GoalLine asks before it plans, so &ldquo;{{ $goal->title }}&rdquo; fits where you're starting from. Skip anything you like.
                @endif
            </p>
        </div>
    </x-slot:hero>

    <div class="mx-auto max-w-2xl">
        @if ($intake->status === App\Models\GoalIntake::GENERATING)
            <section class="rounded-2xl border border-line bg-white px-8 py-10 text-center">
                <h2 class="font-serif text-2xl font-bold text-club tracking-tight">Writing your questions&hellip;</h2>
                <p class="mx-auto mt-2 max-w-md text-ink/70">GoalLine is thinking about what a coach for this goal would ask. This takes a few seconds, and the page will update on its own.</p>
                <div class="mx-auto mt-6 h-1.5 w-40 overflow-hidden rounded-full bg-mint" aria-hidden="true">
                    <div class="h-full w-1/3 animate-pulse rounded-full bg-grass"></div>
                </div>
                <form method="POST" action="{{ route('goals.intake.store', $goal) }}" class="mt-6">
                    @csrf
                    <input type="hidden" name="from" value="{{ $from }}">
                    <button type="submit" name="skip" value="1" class="text-sm font-semibold text-ink/70 hover:text-ink">Skip the questions and build my plan</button>
                </form>
            </section>
        @else
            <form method="POST" action="{{ route('goals.intake.store', $goal) }}" class="space-y-4" data-intake>
                @csrf
                <input type="hidden" name="from" value="{{ $from }}">

                <div class="flex items-center gap-3" data-intake-progress hidden>
                    <p class="shrink-0 text-sm font-medium text-ink/70">Question <span data-intake-current>1</span> of {{ count($questions) }}</p>
                    <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-mint" aria-hidden="true">
                        <div class="h-full rounded-full bg-grass transition-all" data-intake-bar style="width: {{ round(100 / max(1, count($questions))) }}%"></div>
                    </div>
                </div>

                @foreach ($questions as $index => $question)
                    @php
                        $saved = $answers->get($question['id']);
                        $name = "answers[{$question['id']}]";
                        $value = old("answers.{$question['id']}.value", $saved['value'] ?? null);
                        $status = old("answers.{$question['id']}.status", in_array($saved['status'] ?? null, [App\Services\IntakeQuestions::NOT_SURE, App\Services\IntakeQuestions::SKIPPED], true) && $editing ? $saved['status'] : '');
                        $fieldId = 'intake-'.$question['id'];
                    @endphp
                    <fieldset class="rounded-2xl border border-line bg-white p-4" data-intake-question>
                        <legend class="sr-only">Question {{ $index + 1 }}</legend>
                        <p class="font-serif text-xl font-bold text-club tracking-tight">
                            @if (in_array($question['type'], [App\Services\IntakeQuestions::NUMBER, App\Services\IntakeQuestions::SHORT_TEXT], true))
                                <label for="{{ $fieldId }}">{{ $question['text'] }}</label>
                            @else
                                {{ $question['text'] }}
                            @endif
                        </p>
                        @if ($question['why'])
                            <p class="mt-1 text-sm text-ink/70">{{ $question['why'] }}</p>
                        @endif

                        <input type="hidden" name="{{ $name }}[status]" value="{{ $status }}" data-intake-status>

                        <div class="mt-4">
                            @switch($question['type'])
                                @case(App\Services\IntakeQuestions::SINGLE_CHOICE)
                                    <div class="flex flex-wrap gap-2">
                                        @foreach ($question['options'] as $option)
                                            <label class="{{ $chip }}">
                                                <input type="radio" name="{{ $name }}[value]" value="{{ $option }}" class="accent-club" @checked($value === $option)> {{ $option }}
                                            </label>
                                        @endforeach
                                    </div>
                                    @break
                                @case(App\Services\IntakeQuestions::MULTIPLE_CHOICE)
                                    <div class="flex flex-wrap gap-2">
                                        @foreach ($question['options'] as $option)
                                            <label class="{{ $chip }}">
                                                <input type="checkbox" name="{{ $name }}[value][]" value="{{ $option }}" class="accent-club" @checked(in_array($option, (array) $value, true))> {{ $option }}
                                            </label>
                                        @endforeach
                                    </div>
                                    <p class="mt-2 text-sm text-ink/70">Pick any that apply.</p>
                                    @break
                                @case(App\Services\IntakeQuestions::NUMBER)
                                    <div class="flex items-center gap-3">
                                        <input id="{{ $fieldId }}" type="number" name="{{ $name }}[value]" value="{{ $value }}" min="0" step="any" inputmode="decimal"
                                            class="block w-36 rounded-lg border border-line bg-white px-3.5 py-2.5 text-ink outline-none focus:border-club focus:ring-4 focus:ring-club/10">
                                        @if ($question['unit'])
                                            <span class="text-ink/70">{{ $question['unit'] }}</span>
                                        @endif
                                    </div>
                                    @break
                                @case(App\Services\IntakeQuestions::SCALE)
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="text-sm text-ink/70">Not at all</span>
                                        @foreach (range(1, 5) as $step)
                                            <label class="{{ $chip }} px-3.5">
                                                <input type="radio" name="{{ $name }}[value]" value="{{ $step }}" class="sr-only" @checked((string) $value === (string) $step)> {{ $step }}
                                            </label>
                                        @endforeach
                                        <span class="text-sm text-ink/70">Very</span>
                                    </div>
                                    @break
                                @default
                                    <input id="{{ $fieldId }}" type="text" name="{{ $name }}[value]" value="{{ $value }}" maxlength="{{ App\Services\IntakeQuestions::MAX_ANSWER }}"
                                        class="block w-full rounded-lg border border-line bg-white px-3.5 py-2.5 text-ink outline-none focus:border-club focus:ring-4 focus:ring-club/10">
                            @endswitch
                            @error("answers.{$question['id']}.value")
                                <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>
                            @enderror
                            @error("answers.{$question['id']}.value.*")
                                <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mt-5 flex flex-wrap items-center gap-2 border-t border-line pt-4">
                            <button type="button" class="rounded-full border border-line px-4 py-2 text-sm font-medium text-ink/70 transition hover:border-club/40 data-[active]:border-club data-[active]:bg-mint data-[active]:text-club" data-intake-mark="{{ App\Services\IntakeQuestions::NOT_SURE }}">I'm not sure</button>
                            <button type="button" class="rounded-full border border-line px-4 py-2 text-sm font-medium text-ink/70 transition hover:border-club/40 data-[active]:border-club data-[active]:bg-mint data-[active]:text-club" data-intake-mark="{{ App\Services\IntakeQuestions::SKIPPED }}">Skip</button>
                            <span class="flex-1"></span>
                            <button type="button" class="rounded-full px-4 py-2 text-sm font-semibold text-ink/70 hover:text-ink" data-intake-back hidden>Back</button>
                            <button type="button" class="rounded-full bg-club px-5 py-2 text-sm font-semibold text-ivory hover:bg-club-dark" data-intake-next hidden>Next</button>
                        </div>
                    </fieldset>
                @endforeach

                @if ($goal->isPhysical())
                    <p class="rounded-xl bg-mint px-4 py-3 text-sm text-ink/75">GoalLine plans are general guidance, not medical advice. Check with a doctor if you have health concerns.</p>
                @endif

                <div class="flex flex-col-reverse gap-3 pt-2 sm:flex-row sm:items-center sm:justify-between" data-intake-finish>
                    @if ($editing)
                        <a href="{{ route('goals.show', $goal) }}" class="rounded-lg px-4 py-3 text-center font-semibold text-ink/70 hover:text-ink">Cancel</a>
                    @else
                        <button type="submit" name="skip" value="1" formnovalidate class="rounded-lg px-4 py-3 font-semibold text-ink/70 hover:text-ink">Skip all and build my plan</button>
                    @endif
                    <x-form.button class="sm:w-auto sm:px-8">{{ $editing ? 'Save and re-plan' : 'Build my plan' }}</x-form.button>
                </div>
            </form>
        @endif
    </div>
</x-layouts.app>
