{{-- The Challenge builder, for starting one (from a template or "Build your own") and for editing it. Before kickoff
     everything can change; after kickoff only the name, description and visibility (the rules lock, like a GoalLeague
     plan). Each type and duration shows only its own fields (app.js, data-challenge-builder); without scripts every
     field shows and the server still checks it all (ChallengeBuilder). --}}
@php
    $editing = $challenge !== null;
    $v = fn (string $key, $default = null) => old($key, data_get($values, $key, $default));
    $rules = old('rules', $values['rules'] ?? []);
    $type = $v('type');
    $duration = $v('duration');
    $weeklyMode = old('rules.weekly', ! empty($rules['days']) ? 'days' : 'times');
    $input = 'block w-full rounded-lg border border-line bg-white px-3 py-2.5 text-[15px] text-ink outline-none focus:border-gold-ink focus:ring-4 focus:ring-gold/20';
    $label = 'mb-1.5 block text-[13px] font-semibold text-ink/80';
@endphp

<x-layouts.app :title="($editing ? 'Edit '.$challenge->title : 'Start a Challenge').' · GoalLine'">
    <x-slot:hero>
        <x-page-header :title="$editing ? 'Edit Challenge' : 'Start a Challenge'"
            :back="$editing ? route('challenges.show', $challenge) : ($league ? route('leagues.show', $league) : route('leagues.index', ['tab' => 'challenges']))"
            :back-label="$editing ? $challenge->title : ($league ? $league->name : 'Challenges')"
            :subtitle="$league ? 'A side Challenge for '.$league->name.'. It never changes GoalLeague stars.' : 'Pick a template or build your own. Everything can change until kickoff.'" />
    </x-slot:hero>

    <div class="mx-auto max-w-2xl space-y-4" data-first-content>
        @unless ($editing)
            {{-- Templates: tap one to fill the builder. --}}
            <section aria-labelledby="templates-title">
                <h2 id="templates-title" class="eyebrow mb-2 text-gold-ink">Templates</h2>
                <ul class="flex flex-wrap gap-1.5" data-challenge-templates>
                    <li><a href="{{ route('challenges.create', array_filter(['league' => $league?->id])) }}" @class(['inline-flex min-h-11 items-center rounded-full px-3.5 text-[13px] font-semibold ring-1 ring-inset', 'bg-gold text-ink ring-gold' => ! $values['template'], 'bg-white text-ink ring-gold/60 hover:bg-gold-light/40' => $values['template']])>Build your own</a></li>
                    @foreach ($templates as $key => $template)
                        <li><a href="{{ route('challenges.create', array_filter(['template' => $key, 'league' => $league?->id])) }}" title="{{ $template['blurb'] }}"
                            @class(['inline-flex min-h-11 items-center rounded-full px-3.5 text-[13px] font-semibold ring-1 ring-inset', 'bg-gold text-ink ring-gold' => $values['template'] === $key, 'bg-white text-ink ring-gold/60 hover:bg-gold-light/40' => $values['template'] !== $key])>{{ $template['name'] }}</a></li>
                    @endforeach
                </ul>
            </section>
        @endunless

        @if ($locked)
            <p class="rounded-xl border border-gold/60 bg-gold-light/30 px-3 py-2.5 text-[13px] text-ink">This Challenge has started, so its rules are locked. You can still change the name, the description and who can see it.</p>
        @endif

        <form method="POST" action="{{ $editing ? route('challenges.update', $challenge) : route('challenges.store') }}" data-live="false" data-challenge-builder
            class="space-y-5 rounded-2xl border border-line bg-white p-4 sm:p-5">
            @csrf
            @if ($editing)
                @method('PATCH')
            @endif
            @if ($league && ! $editing)
                <input type="hidden" name="league_id" value="{{ $league->id }}">
            @endif
            <input type="hidden" name="template" value="{{ $v('template') }}">

            <div>
                <label for="title" class="{{ $label }}">Name</label>
                <input id="title" name="title" maxlength="{{ App\Models\Challenge::MAX_TITLE }}" required value="{{ $v('title') }}" placeholder="Read 20 pages a day" class="{{ $input }}">
                <p class="mt-1 text-[13px] text-ink/70">Up to {{ App\Models\Challenge::MAX_TITLE }} characters.</p>
                @error('title') <p class="mt-1 text-[13px] text-red-700">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="description" class="{{ $label }}">Description (optional)</label>
                <textarea id="description" name="description" rows="2" maxlength="500" class="{{ $input }}" placeholder="Why we're doing it, or what counts.">{{ $v('description') }}</textarea>
            </div>

            <fieldset @disabled($locked)>
                <legend class="{{ $label }}">Type</legend>
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach (App\Models\Challenge::TYPES as $key => $info)
                        <label class="flex cursor-pointer gap-2.5 rounded-xl border border-line px-3 py-2.5 has-[:checked]:border-gold-ink has-[:checked]:bg-gold-light/30">
                            <input type="radio" name="type" value="{{ $key }}" @checked($type === $key) class="mt-1 accent-[#85651c]" data-challenge-type>
                            <span><span class="block text-[15px] font-semibold text-ink">{{ $info['label'] }}</span><span class="block text-[13px] leading-snug text-ink/75">{{ $info['explain'] }}</span></span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            {{-- The rules for each type. --}}
            <fieldset @disabled($locked) class="space-y-3 rounded-xl bg-ivory px-3 py-3">
                <legend class="sr-only">Rules</legend>
                <div data-type-panel="daily">
                    <p class="{{ $label }}">The checklist <span class="font-normal text-ink/70">(a day counts when every rule is checked)</span></p>
                    @foreach (array_pad(array_slice($rules['items'] ?? [], 0, 6), 6, '') as $index => $item)
                        <label class="sr-only" for="rule-{{ $index }}">Rule {{ $index + 1 }}</label>
                        <input id="rule-{{ $index }}" name="rules[items][]" value="{{ $item }}" maxlength="80" placeholder="{{ ['Read 10 pages', 'Drink your water', 'Work out 30 minutes', 'Journal for 5 minutes', '', ''][$index] }}" class="{{ $input }} mb-1.5">
                    @endforeach
                    @error('rules.items') <p class="text-[13px] text-red-700">{{ $message }}</p> @enderror
                </div>

                <div data-type-panel="weekly" class="space-y-2">
                    <div>
                        <label for="weekly-label" class="{{ $label }}">What you're doing</label>
                        <input id="weekly-label" name="rules[label]" value="{{ $rules['label'] ?? '' }}" maxlength="60" placeholder="Workout" class="{{ $input }}" data-type-input="weekly">
                    </div>
                    <div class="flex flex-wrap gap-3 text-[15px]">
                        <label class="inline-flex min-h-11 items-center gap-2"><input type="radio" name="rules[weekly]" value="times" @checked($weeklyMode === 'times') class="accent-[#85651c]"> Times a week</label>
                        <label class="inline-flex min-h-11 items-center gap-2"><input type="radio" name="rules[weekly]" value="days" @checked($weeklyMode === 'days') class="accent-[#85651c]"> Specific days</label>
                    </div>
                    <div class="flex items-center gap-2">
                        <label for="weekly-times" class="text-[13px] font-semibold text-ink/80">Times a week</label>
                        <input id="weekly-times" name="rules[times]" type="number" min="1" max="7" value="{{ $rules['times'] ?? 3 }}" class="{{ $input }} w-20">
                    </div>
                    <div class="flex flex-wrap gap-1.5" role="group" aria-label="Days of the week">
                        @foreach (App\Models\Habit::DAY_NAMES as $number => $name)
                            <label class="inline-flex min-h-11 min-w-11 cursor-pointer items-center justify-center rounded-lg border border-line px-2 text-[13px] font-semibold has-[:checked]:border-gold-ink has-[:checked]:bg-gold-light/40">
                                <input type="checkbox" name="rules[days][]" value="{{ $number }}" @checked(in_array($number, $rules['days'] ?? [])) class="sr-only">{{ $name }}
                            </label>
                        @endforeach
                    </div>
                    @error('rules.times') <p class="text-[13px] text-red-700">{{ $message }}</p> @enderror
                    @error('rules.days') <p class="text-[13px] text-red-700">{{ $message }}</p> @enderror
                </div>

                <div data-type-panel="abstain">
                    <label for="abstain-label" class="{{ $label }}">What you're staying off</label>
                    <input id="abstain-label" name="rules[label]" value="{{ $rules['label'] ?? '' }}" maxlength="60" placeholder="No sweets" class="{{ $input }}" data-type-input="abstain">
                    <p class="mt-1 text-[13px] text-ink/70">Each day, members check in "Stayed on track", or log "Slipped today" and keep going.</p>
                    @error('rules.label') <p class="mt-1 text-[13px] text-red-700">{{ $message }}</p> @enderror
                </div>

                <div data-type-panel="target" class="grid gap-2 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label for="target-label" class="{{ $label }}">What you're counting</label>
                        <input id="target-label" name="rules[label]" value="{{ $rules['label'] ?? '' }}" maxlength="60" placeholder="Steps" class="{{ $input }}" data-type-input="target">
                    </div>
                    <div>
                        <label for="target-threshold" class="{{ $label }}">Target</label>
                        <input id="target-threshold" name="rules[threshold]" type="number" min="1" step="any" value="{{ $rules['threshold'] ?? '' }}" placeholder="10000" class="{{ $input }}">
                    </div>
                    <div>
                        <label for="target-unit" class="{{ $label }}">Unit</label>
                        <input id="target-unit" name="rules[unit]" maxlength="20" value="{{ $rules['unit'] ?? '' }}" placeholder="steps" class="{{ $input }}">
                    </div>
                    <div>
                        <label for="target-per" class="{{ $label }}">Each</label>
                        <select id="target-per" name="rules[per]" class="{{ $input }}">
                            <option value="day" @selected(($rules['per'] ?? 'day') === 'day')>Day</option>
                            <option value="week" @selected(($rules['per'] ?? 'day') === 'week')>Week (added up)</option>
                        </select>
                    </div>
                    <div>
                        <label for="target-max" class="{{ $label }}">Most anyone could log in a day</label>
                        <input id="target-max" name="rules[max]" type="number" min="1" step="any" value="{{ $rules['max'] ?? '' }}" placeholder="20 × the target" class="{{ $input }}">
                    </div>
                    @error('rules.threshold') <p class="text-[13px] text-red-700 sm:col-span-2">{{ $message }}</p> @enderror
                    @error('rules.max') <p class="text-[13px] text-red-700 sm:col-span-2">{{ $message }}</p> @enderror
                </div>
            </fieldset>

            {{-- When. --}}
            <fieldset @disabled($locked) class="space-y-3">
                <legend class="{{ $label }}">Duration</legend>
                <select name="duration" aria-label="Duration" class="{{ $input }}" data-challenge-duration>
                    @foreach (App\Services\ChallengeBuilder::DURATIONS as $key => $name)
                        <option value="{{ $key }}" @selected($duration === $key)>{{ $name }}</option>
                    @endforeach
                </select>
                <div class="grid gap-2 sm:grid-cols-2">
                    <div data-duration-panel="days custom">
                        <label for="starts_on" class="{{ $label }}">Starts</label>
                        <input id="starts_on" name="starts_on" type="date" value="{{ $v('starts_on') }}" min="{{ App\Models\Challenge::today()->toDateString() }}" class="{{ $input }}" data-challenge-start>
                    </div>
                    <div data-duration-panel="custom">
                        <label for="ends_on" class="{{ $label }}">Ends</label>
                        <input id="ends_on" name="ends_on" type="date" value="{{ $v('ends_on') }}" class="{{ $input }}" data-challenge-end>
                    </div>
                    <div data-duration-panel="month">
                        <label for="month" class="{{ $label }}">Month</label>
                        <input id="month" name="month" type="month" value="{{ $v('month') }}" min="{{ App\Models\Challenge::today()->format('Y-m') }}" class="{{ $input }}" data-challenge-month>
                    </div>
                    <div data-duration-panel="lent">
                        <label for="lent_year" class="{{ $label }}">Lent in</label>
                        <select id="lent_year" name="lent_year" class="{{ $input }}" data-challenge-lent>
                            @foreach ([App\Lent::yearFor(App\Models\Challenge::today()), App\Lent::yearFor(App\Models\Challenge::today()) + 1] as $year)
                                @if ($year <= App\Models\Challenge::today()->year + 1)
                                    <option value="{{ $year }}" @selected((int) $v('lent_year') === $year) data-start="{{ App\Lent::ashWednesday($year)->toDateString() }}" data-end="{{ App\Lent::holySaturday($year)->toDateString() }}">{{ $year }}: {{ App\Lent::ashWednesday($year)->format('M j') }} to {{ App\Lent::holySaturday($year)->format('M j') }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                </div>
                <p class="text-[13px] text-ink/75" data-challenge-dates aria-live="polite">Kickoff is 12:00 a.m. New York time on the start date.</p>
                @error('starts_on') <p class="text-[13px] text-red-700">{{ $message }}</p> @enderror
                @error('ends_on') <p class="text-[13px] text-red-700">{{ $message }}</p> @enderror
                @error('month') <p class="text-[13px] text-red-700">{{ $message }}</p> @enderror
                @error('lent_year') <p class="text-[13px] text-red-700">{{ $message }}</p> @enderror
            </fieldset>

            {{-- How strict. --}}
            <fieldset @disabled($locked) class="space-y-2">
                <legend class="{{ $label }}">Mode</legend>
                <label class="flex cursor-pointer gap-2.5 rounded-xl border border-line px-3 py-2.5 has-[:checked]:border-gold-ink has-[:checked]:bg-gold-light/30">
                    <input type="radio" name="mode" value="flexible" @checked($v('mode') === 'flexible') class="mt-1 accent-[#85651c]">
                    <span><span class="block text-[15px] font-semibold">Flexible</span><span class="block text-[13px] text-ink/75">A few misses or rest days are allowed before a miss counts.</span></span>
                </label>
                <label class="flex cursor-pointer gap-2.5 rounded-xl border border-line px-3 py-2.5 has-[:checked]:border-gold-ink has-[:checked]:bg-gold-light/30">
                    <input type="radio" name="mode" value="strict" @checked($v('mode') === 'strict') class="mt-1 accent-[#85651c]">
                    <span><span class="block text-[15px] font-semibold">Strict</span><span class="block text-[13px] text-ink/75">Miss a day (or week) and your streak restarts. Restarts show in the Standings.</span></span>
                </label>
                <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                    <label class="inline-flex items-center gap-2 text-[13px] font-semibold text-ink/80">Misses allowed (flexible)
                        <input name="allowed_misses" type="number" min="0" max="{{ App\Models\Challenge::MAX_MISSES }}" value="{{ $v('allowed_misses', 0) }}" class="{{ $input }} w-20">
                    </label>
                    <label class="inline-flex min-h-11 items-center gap-2 text-[15px]" data-type-hide="weekly">
                        <input type="hidden" name="sundays_off" value="0">
                        <input type="checkbox" name="sundays_off" value="1" @checked($v('sundays_off')) class="size-5 accent-[#85651c]"> Sundays off <span class="text-[13px] text-ink/70">(not required, never break a streak)</span>
                    </label>
                </div>
            </fieldset>

            {{-- Who. --}}
            <fieldset class="space-y-2">
                <legend class="{{ $label }}">Who can see it</legend>
                @if ($league)
                    <p class="text-[13px] text-ink/75">Members of {{ $league->name }} only.</p>
                    <input type="hidden" name="visibility" value="private">
                @else
                    <div class="flex flex-wrap gap-2">
                        <label class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-line px-3 has-[:checked]:border-gold-ink has-[:checked]:bg-gold-light/30">
                            <input type="radio" name="visibility" value="private" @checked($v('visibility') === 'private') class="accent-[#85651c]"> Private <span class="text-[13px] text-ink/70">(invite only)</span>
                        </label>
                        <label class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-line px-3 has-[:checked]:border-gold-ink has-[:checked]:bg-gold-light/30">
                            <input type="radio" name="visibility" value="public" @checked($v('visibility') === 'public') class="accent-[#85651c]"> Public <span class="text-[13px] text-ink/70">(anyone can find and join)</span>
                        </label>
                    </div>
                    <p class="text-[13px] text-ink/70">Public shows the name, rules, dates, member count and your display name. Never emails or anyone's check-in details.</p>
                @endif
                <label @disabled($locked) class="inline-flex items-center gap-2 text-[13px] font-semibold text-ink/80">Member cap (optional)
                    <input name="max_members" type="number" min="2" max="{{ App\Models\Challenge::MAX_MEMBERS }}" value="{{ $v('max_members') }}" @disabled($locked) class="{{ $input }} w-24" placeholder="None">
                </label>
                @error('max_members') <p class="text-[13px] text-red-700">{{ $message }}</p> @enderror
            </fieldset>

            <button type="submit" class="inline-flex min-h-12 w-full items-center justify-center rounded-full bg-gold px-6 text-[16px] font-bold text-ink hover:brightness-105">{{ $editing ? 'Save' : 'Start the Challenge' }}</button>
        </form>
    </div>
</x-layouts.app>
