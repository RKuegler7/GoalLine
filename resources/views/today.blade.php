<x-layouts.app title="Today · GoalLine">
    <x-slot:hero>
        <div class="mx-auto max-w-5xl px-4 pt-6 pb-12">
            <a href="{{ route('dashboard') }}" class="text-sm font-medium text-ivory/75 hover:text-ivory">&larr; Clubhouse</a>
            <p class="eyebrow mt-6 text-mint">{{ $gameplan->today->format('l, F j') }}</p>
            <h1 class="mt-3 font-serif text-4xl font-bold tracking-tight text-ivory sm:text-5xl">Today</h1>
            <p class="mt-3 max-w-xl text-ivory/85">Trust the process. Show up today.</p>
        </div>
    </x-slot:hero>

    <x-on-the-line :streak="$streak" />

    <x-todays-gameplan :gameplan="$gameplan" return="today" class="mt-6" />

    <x-habit-grid :gameplan="$gameplan" class="mt-6" />
</x-layouts.app>
