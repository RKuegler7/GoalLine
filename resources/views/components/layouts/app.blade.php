@props(['title' => 'GoalLine'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head')
</head>
<body class="min-h-screen bg-ivory font-sans text-ink antialiased">
    <section class="bg-club">
        <header class="mx-auto flex max-w-5xl items-center justify-between gap-4 px-4 py-5">
            <a href="{{ route('dashboard') }}" class="rounded-sm focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-mint">
                <x-brand.logo class="h-7 sm:h-8" />
            </a>

            <div class="flex items-center gap-4">
                <span class="hidden text-sm font-medium text-ivory/85 sm:inline">{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="rounded-full border border-ivory/60 px-4 py-1.5 text-sm font-medium text-ivory transition hover:border-ivory hover:bg-ivory hover:text-club">
                        Log out
                    </button>
                </form>
            </div>
        </header>

        {{ $hero ?? '' }}
    </section>

    <x-goal-line />

    <main class="mx-auto max-w-5xl px-4 py-10">
        {{ $slot }}
    </main>
</body>
</html>
