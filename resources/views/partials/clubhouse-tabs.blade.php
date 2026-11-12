{{-- The Clubhouse's two views: what's in play now, and everything finished. The choice lives in the URL (?tab=history),
     so back, refresh and links all land on the same view. --}}
<x-segmented label="Clubhouse view" class="mb-3" :options="[
    ['label' => 'Now', 'href' => route('dashboard'), 'active' => ($active ?? 'now') === 'now'],
    ['label' => 'History', 'href' => route('dashboard', ['tab' => 'history']), 'active' => ($active ?? 'now') === 'history'],
]" />
