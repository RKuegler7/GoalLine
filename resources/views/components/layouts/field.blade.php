{{-- Public pages on Club Green. With a description, the page gets a meta description and a link preview (Open Graph
     and Twitter card) with the branded preview image; pages with their own preview (shared cards) pass share=false. --}}
@props(['title' => 'GoalLine', 'description' => null, 'share' => true, 'headerLogo' => true])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head')
    @if ($share && $description)
        @include('partials.share-meta', [
            'title' => $title,
            'description' => $description,
            'url' => url()->current(),
            'image' => App\ShareLink::to('/images/og-goalline.jpg'),
            'imageWidth' => 1200,
            'imageHeight' => 630,
            'imageAlt' => 'GoalLine: get in line with your goals.',
        ])
    @endif
</head>
<body class="flex min-h-dvh flex-col bg-ivory pr-[env(safe-area-inset-right)] pb-[env(safe-area-inset-bottom)] pl-[env(safe-area-inset-left)] font-sans text-ink antialiased">
    <x-splash />
    {{-- Clear of the iPhone's clock, notch and battery when opened from the Home Screen, like the signed-in bar. --}}
    <section class="relative overflow-hidden bg-club pt-[env(safe-area-inset-top)]">
        <x-field-backdrop class="absolute inset-0 size-full" />

        <div class="relative">
            <header class="mx-auto flex max-w-5xl items-center justify-between px-4 py-5">
                @if ($headerLogo)
                    <a href="{{ route('home') }}" class="rounded-sm focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-mint">
                        <x-brand.logo class="h-7 sm:h-8" />
                    </a>
                @else
                    {{-- The page shows the big lockup itself. --}}
                    <span></span>
                @endif
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
        <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-x-6 gap-y-2 px-4 py-5">
            <x-brand.wordmark tone="dark" class="h-5" />
            <nav class="flex flex-wrap items-center justify-end gap-x-4 gap-y-1 text-sm text-ink/70" aria-label="Footer">
                <a href="{{ route('about') }}" class="inline-flex min-h-11 items-center hover:text-club">About</a>
                <a href="{{ route('privacy') }}" class="inline-flex min-h-11 items-center hover:text-club">Privacy and safety</a>
                <a href="{{ auth()->check() ? route('help') : route('about').'#faq' }}" class="inline-flex min-h-11 items-center hover:text-club">Help</a>
                <a href="{{ route('terms') }}" class="inline-flex min-h-11 items-center hover:text-club">Terms</a>
                <a href="{{ config('goalline.repository_url') }}" class="inline-flex min-h-11 items-center hover:text-club" rel="noopener">GitHub</a>
            </nav>
        </div>
    </footer>
</body>
</html>
