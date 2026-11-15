{{-- Every public GoalLeague you can join, with search and filters (the GoalLeagues tab's "See all"). --}}
<x-layouts.app title="Public GoalLeagues · GoalLine">
    <x-slot:hero>
        <x-page-header title="Public GoalLeagues" :back="route('leagues.index')" back-label="GoalLeagues" subtitle="Open to anyone. Join with your own goal; the league goal is filled in to start." />
    </x-slot:hero>

    <div class="space-y-3" data-first-content>
        @include('partials.public-filters', [
            'action' => route('leagues.browse'), 'kindLabel' => 'Category',
            'kinds' => collect(App\GoalCategory::cases())->mapWithKeys(fn ($category) => [$category->value => $category->label()])->all(),
            'durations' => App\Services\PublicListings::LEAGUE_DURATIONS,
        ])
        @if ($leagues->isEmpty())
            <x-empty-state message="No public GoalLeagues match. Try fewer filters, or start your own and make it public." action="Start a GoalLeague" :href="route('leagues.create')" />
        @else
            <ul class="grid gap-2 sm:grid-cols-2" data-public-leagues>
                @foreach ($leagues as $league)
                    <x-public-league-card :league="$league" :member="isset($memberOf[$league->id])" :example="$example" />
                @endforeach
            </ul>
        @endif
    </div>
</x-layouts.app>
