{{-- A goal's recurring habits: what they are, how often, and a way to add, change, or retire them. --}}
@props(['goal', 'return' => null])

@php
    $habits = $goal->habits->whereNull('archived_at');
    $canEdit = $goal->isActive();
    $week = $goal->currentWeek();
    $habitErrors = $errors->hasAny(['title', 'times_per_week', 'days', 'days.*']);
@endphp

@if ($habits->isNotEmpty() || $canEdit)
    <section id="habits" {{ $attributes->class('mt-6 scroll-mt-6 rounded-2xl border border-line bg-white px-6 py-5') }}>
        <div class="flex items-center justify-between gap-4">
            <h2 class="eyebrow flex items-center gap-1.5 text-club"><x-icon name="repeat" class="size-4" /> Habits</h2>
            <span class="text-sm text-ink/50">Count toward every GoalWeek</span>
        </div>

        @if ($habits->isEmpty())
            <p class="mt-2 text-ink/70">
                Most goals are won by showing up again and again. Add the recurring things that move this goal, like &ldquo;Run&rdquo; 4&times; a week or &ldquo;Study 2 hours&rdquo; every weekday. Each one counts toward your GoalWeek result.
            </p>
        @else
            <ul class="mt-3 divide-y divide-line">
                @foreach ($habits as $habit)
                    <li class="py-3">
                        <details>
                            <summary class="flex cursor-pointer list-none items-center gap-3 [&::-webkit-details-marker]:hidden">
                                <span class="min-w-0 flex-1">
                                    <span class="block font-medium text-ink">{{ $habit->title }}</span>
                                    <span class="block text-sm text-ink/55">{{ $habit->cadence() }}</span>
                                </span>
                                @if ($week?->isStarted())
                                    <x-habit-week :habit="$habit" :week="$week" />
                                @endif
                                @if ($canEdit)
                                    <span class="text-xs font-semibold text-club">Edit</span>
                                @endif
                            </summary>
                            @if ($canEdit)
                                <form method="POST" action="{{ route('habits.update', $habit) }}" class="mt-3 rounded-xl bg-ivory p-4">
                                    @csrf
                                    @method('PUT')
                                    @if ($return)
                                        <input type="hidden" name="return" value="{{ $return }}">
                                    @endif
                                    <x-habit-fields :habit="$habit" :prefix="'habit-'.$habit->id" />
                                    <div class="mt-4 flex flex-wrap items-center gap-2">
                                        <button type="submit" class="rounded-full bg-club px-4 py-1.5 text-sm font-semibold text-ivory hover:bg-club-dark">Save</button>
                                        <button type="submit" form="retire-habit-{{ $habit->id }}" class="rounded-full px-4 py-1.5 text-sm font-semibold text-red-700 hover:bg-red-50">Retire habit</button>
                                    </div>
                                </form>
                                <form id="retire-habit-{{ $habit->id }}" method="POST" action="{{ route('habits.destroy', $habit) }}"
                                    onsubmit="return confirm('Retire this habit? It stops counting from now on. Past GoalWeeks keep their results.')">
                                    @csrf
                                    @method('DELETE')
                                    @if ($return)
                                        <input type="hidden" name="return" value="{{ $return }}">
                                    @endif
                                </form>
                            @endif
                        </details>
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($canEdit)
            <details class="mt-3" @if ($habitErrors) open @endif>
                <summary class="inline-flex cursor-pointer list-none items-center gap-1 rounded-full border border-dashed border-club/40 px-4 py-1.5 text-sm font-semibold text-club hover:border-club [&::-webkit-details-marker]:hidden">
                    + Add a habit
                </summary>
                <form method="POST" action="{{ route('habits.store', $goal) }}" class="mt-3 rounded-xl bg-ivory p-4">
                    @csrf
                    @if ($return)
                        <input type="hidden" name="return" value="{{ $return }}">
                    @endif
                    <x-habit-fields :prefix="'new-habit-'.$goal->id" />
                    @if ($habitErrors)
                        <p class="mt-2 text-sm text-red-700">{{ $errors->first('title') ?: $errors->first('times_per_week') ?: 'Pick days from Monday to Sunday.' }}</p>
                    @endif
                    <button type="submit" class="mt-4 rounded-full bg-club px-4 py-1.5 text-sm font-semibold text-ivory hover:bg-club-dark">Add habit</button>
                </form>
            </details>
        @endif
    </section>
@endif
