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
                <a href="{{ route('leagues.index') }}" class="text-sm font-semibold text-ivory/85 hover:text-ivory">Leagues</a>
                <a href="{{ route('profile.show') }}" class="flex items-center gap-2 rounded-full py-1 pr-1 text-sm font-medium text-ivory/90 hover:text-ivory sm:pr-3">
                    <x-avatar :user="auth()->user()" tone="light" class="size-8 text-xs" />
                    <span class="hidden sm:inline">{{ auth()->user()->name }}</span>
                </a>
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
        @if (session('status'))
            <p role="status" class="mb-6 flex items-center gap-3 rounded-xl border border-grass/30 bg-mint px-4 py-3 text-sm font-medium text-club">
                <span class="size-2 rounded-full bg-grass" aria-hidden="true"></span>
                {{ session('status') }}
            </p>
        @endif

        {{ $slot }}
    </main>
</body>
</html>
