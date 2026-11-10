{{-- Error pages: the GoalLine look, a calm sentence, and a way home. Standalone on purpose: no database, session or
     sign-in lookups, so it still renders when something underneath is down. Never shows technical details. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#176b45">
    <meta name="robots" content="noindex">
    <title>@yield('title') · GoalLine</title>
    <link rel="icon" href="/favicon.ico" sizes="48x48">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    @fonts
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-dvh flex-col bg-ivory pr-[env(safe-area-inset-right)] pb-[env(safe-area-inset-bottom)] pl-[env(safe-area-inset-left)] font-sans text-ink antialiased">
    <header class="bg-club pt-[env(safe-area-inset-top)]">
        <div class="mx-auto flex h-14 max-w-3xl items-center px-4">
            <a href="/" aria-label="GoalLine home"><x-brand.logo class="h-6" /></a>
        </div>
    </header>
    <x-goal-line />

    <main class="mx-auto flex w-full max-w-xl flex-1 flex-col items-center justify-center px-4 py-12 text-center" data-error-page="@yield('code')">
        <svg viewBox="0 0 300 24" class="h-6 w-56" aria-hidden="true">
            <line x1="6" y1="12" x2="278" y2="12" stroke="#43b96a" stroke-opacity="0.3" stroke-width="4" stroke-linecap="round" />
            <line x1="286" y1="3" x2="286" y2="21" stroke="#176b45" stroke-width="3" stroke-linecap="round" />
            <circle cx="120" cy="12" r="7" fill="#176b45" />
        </svg>
        <p class="eyebrow mt-5 text-[12px] text-club/80">Error @yield('code')</p>
        <h1 class="mt-1 font-serif text-[28px] leading-tight font-bold tracking-tight text-club">@yield('title')</h1>
        <p class="mt-2 max-w-md text-[15px] text-ink/80">@yield('message')</p>
        <div class="mt-6 flex flex-wrap items-center justify-center gap-2">
            @yield('actions')
            <a href="/" class="inline-flex min-h-11 items-center rounded-full bg-club px-5 text-sm font-semibold text-ivory hover:bg-club-dark">Back to GoalLine</a>
        </div>
    </main>

    <footer class="border-t border-line">
        <p class="mx-auto max-w-3xl px-4 py-5 text-sm text-ink/70">Get in line with your goals.</p>
    </footer>
</body>
</html>
