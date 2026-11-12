@php($rules = app(App\Services\ScoringRules::class))
<x-layouts.welcome :step="4" title="How the game works" intro="Thirty seconds, then you're in the Clubhouse.">
    <div class="space-y-4">
        <ol class="space-y-3">
            @foreach ([
                ['Every week is a GoalWeek', 'Monday to Sunday on your clock. Each one has planned tasks, your recurring habits, and one milestone from your roadmap.'],
                ['Check in as you go', 'Trust the process: show up today. Check off tasks and log habits for today or yesterday. Add your own tasks mid-week and they count as bonus work.'],
                ['Your result', 'Your share of the week\'s plan points sets your band: '.collect($rules->bands())->map(fn ($band) => $band['label'].' '.$band['range'])->implode(', ').' (Smashed needs proof or your opponent\'s OK). That earns line stars: '.collect($rules->bands())->map(fn ($band) => $band['points'])->implode(', ').'.'],
                ['GoalLeagues (optional)', 'Each GoalWeek you face one league mate in the GoalWeek Matchup. '.collect($rules->outcomes())->map(fn ($outcome) => $outcome['label'].' '.$outcome['points'])->implode(', ').' matchup stars. Same band is a Photo finish: the higher percent of plan takes it. With line stars, '.$rules->weekRange()['min'].' to '.$rules->weekRange()['max'].' stars a week.'],
                ['Your coach has your back', 'Change your plan anytime. Your coach checks you are still in line and suggests a fix if not.'],
            ] as [$heading, $body])
                <li class="flex gap-4 rounded-2xl border border-line bg-white px-4 py-3">
                    <span class="font-serif text-2xl font-bold text-club tabular-nums tracking-tight">{{ $loop->iteration }}</span>
                    <div>
                        <p class="font-semibold text-ink">{{ $heading }}</p>
                        <p class="mt-0.5 text-sm text-ink/70">{{ $body }}</p>
                    </div>
                </li>
            @endforeach
        </ol>

        <form method="POST" action="{{ route('welcome.finish') }}" class="flex justify-end pt-2">
            @csrf
            <button type="submit" class="rounded-full bg-club px-7 py-3 font-semibold text-ivory shadow-lg shadow-club/20 hover:bg-club-dark">Enter the Clubhouse</button>
        </form>
    </div>
</x-layouts.welcome>
