<x-layouts.welcome :step="4" title="How the game works" intro="Thirty seconds, then you're in the Clubhouse.">
    <div class="space-y-4">
        <ol class="space-y-3">
            @foreach ([
                ['Every week is a GoalWeek', 'Monday to Sunday on your clock. Each one has planned tasks and one milestone from your roadmap.'],
                ['Check in as you go', 'Check off tasks and your milestone from the Clubhouse. Add your own tasks mid-week and they count as bonus work.'],
                ['Your result', 'Short of the line under 70% of tasks, In line at 70%+, Past the line with every task and the milestone, Smashed the line with bonus work on top. That earns 0 to 3 points.'],
                ['GoalLeagues (optional)', 'Each week you face a GoalGroup opponent. A better result wins 3 points, a tie 1. Up to 6 points a week.'],
                ['Your coach has your back', 'Change your plan anytime. Your coach checks you are still in line and suggests a fix if not.'],
            ] as [$heading, $body])
                <li class="flex gap-4 rounded-2xl border border-line bg-white px-5 py-4">
                    <span class="font-serif text-2xl font-medium text-grass tabular-nums">{{ $loop->iteration }}</span>
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
