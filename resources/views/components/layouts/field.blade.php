@props(['title' => 'GoalLine'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head')
</head>
<body class="flex min-h-dvh flex-col bg-ivory pr-[env(safe-area-inset-right)] pb-[env(safe-area-inset-bottom)] pl-[env(safe-area-inset-left)] font-sans text-ink antialiased">
    <x-splash />
    <section class="relative overflow-hidden bg-club">
        <x-field-backdrop class="absolute inset-0 size-full" />

        <div class="relative">
            <header class="mx-auto flex max-w-5xl items-center justify-between px-4 py-5">
                <a href="{{ route('home') }}" class="rounded-sm focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-mint">
                    <x-brand.logo class="h-7 sm:h-8" />
                </a>
                <nav class="flex items-center gap-5" aria-label="Main">
                    <a href="{{ route('about') }}" @if (request()->routeIs('about')) aria-current="page" @endif class="text-sm font-medium text-ivory/90 hover:text-ivory">About</a>
                    @isset($nav)
                        {{ $nav }}
                    @endisset
                </nav>
            </header>

            {{ $hero ?? '' }}
        </div>
    </section>

    <x-goal-line />

    <main class="flex-1">
        {{ $slot }}
    </main>

    <footer class="border-t border-line">
        <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-6">
            <x-brand.wordmark tone="dark" class="h-5" />
            <nav class="flex items-center gap-4 text-sm text-ink/70" aria-label="Footer">
                <a href="{{ route('about') }}" class="hover:text-club">About</a>
                <a href="{{ route('privacy') }}" class="hover:text-club">Privacy</a>
                <a href="{{ route('terms') }}" class="hover:text-club">Terms</a>
                <span class="eyebrow hidden text-club sm:inline">Get in line.</span>
            </nav>
        </div>
    </footer>
</body>
</html>
