{{-- One member's Challenge as its own page (the Standings open the same content in a sheet). --}}
<x-layouts.app :title="$member->user->name.' · '.$challenge->title.' · GoalLine'">
    <x-slot:hero>
        <x-page-header :title="$member->user->name" :eyebrow="$challenge->title" :back="route('challenges.show', $challenge)" back-label="Standings" />
    </x-slot:hero>

    <div class="mx-auto max-w-xl" data-first-content>
        @include('challenges.partials.member')
    </div>
</x-layouts.app>
