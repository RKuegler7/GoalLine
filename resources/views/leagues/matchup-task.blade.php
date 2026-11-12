<x-layouts.app :title="$row['task']->description.' · GoalWeek '.$week.' Matchup'">
    <x-slot:hero>
        <div class="mx-auto max-w-5xl px-4 pt-6 pb-10">
            <x-back-to :href="route('leagues.matchups', [$league, $week])" label="GoalWeek {{ $week }} Matchup" />
            <p class="eyebrow mt-5 text-mint">{{ $league->name }}</p>
        </div>
    </x-slot:hero>

    <div class="mx-auto max-w-lg rounded-3xl border border-line bg-white p-5">
        @include('leagues.partials.task-sheet')
    </div>
</x-layouts.app>
