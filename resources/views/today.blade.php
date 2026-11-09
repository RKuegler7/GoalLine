<x-layouts.app title="Today · GoalLine">
    <x-slot:hero>
        <x-page-header title="Today" :back="route('dashboard')" back-label="Clubhouse" :eyebrow="$gameplan->today->format('l, F j')" subtitle="Trust the process. Show up today." />
    </x-slot:hero>

    <x-on-the-line :streak="$streak" />

    <x-todays-gameplan :gameplan="$gameplan" return="today" class="mt-6" />

    <x-habit-grid :gameplan="$gameplan" class="mt-6" />
</x-layouts.app>
