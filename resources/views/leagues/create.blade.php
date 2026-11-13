<x-layouts.app title="Start a GoalLeague · GoalLine">
    <x-slot:hero>
        <div class="mx-auto max-w-5xl px-4 pt-6 pb-12">
            <x-back-to :href="route('leagues.index')" label="GoalLeagues" />
            <h1 class="mt-6 font-serif text-4xl font-bold tracking-tight text-ivory sm:text-5xl">Start a GoalLeague</h1>
            <p class="mt-3 max-w-xl text-ivory/85">Everyone joins before kickoff and plays the whole season together.</p>
        </div>
    </x-slot:hero>

    @php
        // An idea from Explore's Inspiration strip arrives as ?name=&category=&goal_title=, filled in below.
        $idea = ['name' => Str::limit(trim((string) request('name')), 80, ''), 'category' => App\GoalCategory::tryFrom((string) request('category'))?->value, 'goal' => Str::limit(trim((string) request('goal_title')), 120, '')];
        $goalChoice = old('goal_id', $fromGoal?->id ?? ($goals->isEmpty() || $idea['goal'] !== '' ? 'new' : $goals->first()->id));
    @endphp
        <form method="POST" action="{{ route('leagues.store') }}" class="mx-auto max-w-2xl space-y-6 rounded-2xl border border-line bg-white p-4 sm:p-6">
            @csrf

            @if ($rematch ?? null)
                <input type="hidden" name="rematch_of" value="{{ $rematch->id }}">
                <p class="rounded-xl bg-mint px-4 py-3 text-sm text-club" data-rematch-note>Running back {{ $rematch->name }}. Everyone from last season gets an invite in the app when you create it.</p>
            @endif

            @if ($fromGoal)
                <p class="rounded-xl bg-mint px-4 py-3 text-sm text-club">Filled in from &ldquo;{{ $fromGoal->title }}&rdquo;. Change anything you like. Next you'll invite your people.</p>
            @endif

            <x-form.input name="name" label="GoalLeague name" maxlength="80" placeholder="FAR Exam Crew" :value="($rematch ?? null) ? App\Services\LeagueJoin::nextSeasonName($rematch->name) : ($fromGoal ? Str::limit($fromGoal->title, 70, '').' League' : ($idea['name'] ?: null))" required autofocus />

            <div>
                <label for="category" class="mb-1.5 block text-sm font-medium text-ink/80">Kind of goal</label>
                <select id="category" name="category" class="block w-full rounded-lg border border-line bg-white px-3.5 py-2.5 text-ink outline-none focus:border-club focus:ring-4 focus:ring-club/10">
                    @foreach (App\GoalCategory::cases() as $category)
                        <option value="{{ $category->value }}" @selected(old('category', $fromGoal?->category?->value ?? ($rematch ?? null)?->category?->value ?? $idea['category']) === $category->value)>{{ $category->label() }}</option>
                    @endforeach
                </select>
            </div>
            <x-form.textarea name="description" label="What's this league about? (optional)" maxlength="280"
                hint="For example: everyone sitting for FAR on December 15." />

            <div>
                <label for="goal_id" class="mb-1.5 block text-sm font-medium text-ink/80">Your goal in this league</label>
                <select id="goal_id" name="goal_id" required data-reveal="new-league-goal" data-reveal-value="new"
                    class="block w-full rounded-lg border border-line bg-white px-3.5 py-2.5 text-ink outline-none focus:border-club focus:ring-4 focus:ring-club/10">
                    @foreach ($goals as $goal)
                        <option value="{{ $goal->id }}" @selected((string) $goalChoice === (string) $goal->id)>{{ $goal->title }} (by {{ $goal->target_date->format('M j, Y') }})</option>
                    @endforeach
                    <option value="new" @selected($goalChoice === 'new')>+ A new goal for this league</option>
                </select>
                @error('goal_id')
                    <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>
                @enderror

                <div id="new-league-goal" class="mt-3 space-y-4 rounded-xl bg-mint/60 p-4">
                    <p class="text-sm text-club">Name the goal you'll play here. After you create the league, GoalLine asks a few questions and builds its plan.</p>
                    <x-form.input name="goal_title" label="Your goal" maxlength="120" :value="$idea['goal'] ?: null" :placeholder="App\GoalExamples::title($fromGoal?->category)" />
                    <x-form.input name="goal_target_date" label="Done by" type="date" :min="App\Clock::today()->addDay()->format('Y-m-d')"
                        hint="Usually the same as the season's end." />
                </div>
            </div>

            <div class="grid gap-6 sm:grid-cols-2">
                <div>
                    <label for="starts_on" class="mb-1.5 block text-sm font-medium text-ink/80">Kickoff</label>
                    <select id="starts_on" name="starts_on" required data-season-start class="block w-full rounded-lg border border-line bg-white px-3.5 py-2.5 text-ink outline-none focus:border-club focus:ring-4 focus:ring-club/10">
                        @foreach ($kickoffs as $monday)
                            <option value="{{ $monday->toDateString() }}" @selected(old('starts_on', $kickoffs[0]->toDateString()) === $monday->toDateString())>Monday, {{ $monday->format('M j') }}{{ $monday->isToday() ? ' (today)' : '' }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1.5 text-sm text-ink/70">Week 1 starts Monday at 12:00 a.m. Eastern. Check-ins open then; until kickoff everyone can shape their plan.</p>
                    <p class="mt-1 text-sm font-semibold text-club" data-season-length aria-live="polite"></p>
                    @error('starts_on')
                        <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>
                    @enderror
                </div>

                <x-form.input name="ends_on" label="Season ends" type="date" :value="($fromGoal ?? $goals->first())?->target_date->format('Y-m-d')"
                    hint="Usually your shared deadline. We round to that week's Sunday." required />
            </div>

            <p class="rounded-xl bg-ivory px-4 py-3 text-sm text-ink/70">
                GoalLeagues hold up to {{ App\Models\League::MAX_MEMBERS }} people; {{ App\Models\League::RECOMMENDED_MEMBERS }} or fewer works best, so everyone knows each other.
                Each GoalWeek you face one league mate in the GoalWeek Matchup, rotating round-robin. With an odd number of members, one person plays the league median.
            </p>

            <div class="flex flex-col-reverse gap-3 border-t border-line pt-6 sm:flex-row sm:items-center sm:justify-end">
                <a href="{{ route('leagues.index') }}" class="rounded-lg px-4 py-3 text-center font-semibold text-ink/70 transition hover:text-ink">Cancel</a>
                <x-form.button class="sm:w-auto sm:px-8">Create GoalLeague</x-form.button>
            </div>
        </form>
</x-layouts.app>
