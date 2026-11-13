{{-- Highlights: your people's feed, or Explore (public GoalLeagues and public posts). --}}
<x-segmented label="Highlights" class="mx-auto mb-4 w-full max-w-sm [&_a]:min-h-11" data-highlights-switch :options="[
    ['label' => 'Friends & leagues', 'href' => route('highlights.index'), 'active' => $active === 'feed'],
    ['label' => 'Explore', 'href' => route('highlights.explore'), 'active' => $active === 'explore'],
]" />
