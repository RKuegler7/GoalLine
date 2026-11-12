{{-- Help as a page of its own, for when scripts are off or someone opens /help directly. --}}
<x-layouts.app title="Help · GoalLine">
    <x-slot:hero>
        <x-page-header title="Help" subtitle="Quick answers, the tour, and a way to reach us." />
    </x-slot:hero>

    <div class="mx-auto max-w-2xl" data-first-content>
        @include('help.panel')
    </div>
</x-layouts.app>
