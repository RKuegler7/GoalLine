@props(['title' => 'GoalLine'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head')
</head>
<body class="flex min-h-screen flex-col bg-ivory font-sans text-ink antialiased">
    <section class="relative overflow-hidden bg-club">
        <x-field-backdrop class="absolute inset-0 size-full" />

        <div class="relative">
            <header class="mx-auto flex max-w-5xl items-center justify-between px-4 py-5">
                <a href="{{ route('home') }}" class="rounded-sm focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-mint">
                    <x-brand.logo class="h-7 sm:h-8" />
                </a>
                @isset($nav)
                    {{ $nav }}
                @endisset
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
            <span class="eyebrow text-club">Get in line.</span>
        </div>
    </footer>
</body>
</html>
