{{-- A plain-language legal page: privacy policy or terms. --}}
@props(['title', 'updated'])

<x-layouts.field :title="$title.' · GoalLine'">
    <x-slot:nav>
        <a href="{{ auth()->check() ? route('dashboard') : route('login') }}" class="text-sm font-medium text-ivory/90 hover:text-ivory">{{ auth()->check() ? 'Clubhouse' : 'Log in' }}</a>
    </x-slot:nav>

    <x-slot:hero>
        <div class="mx-auto max-w-3xl px-4 pt-8 pb-14">
            <h1 class="font-serif text-4xl font-medium tracking-tight text-ivory sm:text-5xl">{{ $title }}</h1>
            <p class="mt-3 text-ivory/80">Last updated {{ $updated }}</p>
        </div>
    </x-slot:hero>

    <article class="mx-auto max-w-3xl space-y-6 px-4 py-10 leading-relaxed text-ink/85 [&_h2]:mt-10 [&_h2]:font-serif [&_h2]:text-2xl [&_h2]:font-medium [&_h2]:text-club [&_li]:ml-5 [&_li]:list-disc [&_ul]:space-y-1.5">
        {{ $slot }}
    </article>
</x-layouts.field>
