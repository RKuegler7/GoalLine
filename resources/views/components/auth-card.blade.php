@props(['title', 'eyebrow', 'intro'])

<x-layouts.field :title="$title.' · GoalLine'">
    <x-slot:hero>
        <div class="mx-auto max-w-md px-4 pt-8 pb-24 text-center">
            <p class="eyebrow text-mint">{{ $eyebrow }}</p>
            <h1 class="mt-3 font-serif text-4xl font-medium tracking-tight text-ivory">{{ $title }}</h1>
            <p class="mt-3 text-ivory/85">{{ $intro }}</p>
        </div>
    </x-slot:hero>

    {{-- The card sits across the goal line. --}}
    <div class="relative -mt-16 px-4 pb-16">
        <div class="mx-auto w-full max-w-md rounded-2xl border border-line bg-white p-8 shadow-xl shadow-ink/5">
            {{ $slot }}
        </div>
    </div>
</x-layouts.field>
