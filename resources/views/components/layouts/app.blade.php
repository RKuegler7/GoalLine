@props(['title' => 'GoalLine'])

@php
    $viewer = auth()->user();
    $unread = $viewer->unreadLeagueMessages();
    $inboxCount = $viewer->unreadNotifications()->count();
    $onMatchups = request()->routeIs('leagues.matchups*', 'matchups');
    $tabs = [
        ['label' => 'Clubhouse', 'route' => 'dashboard', 'active' => request()->routeIs('dashboard', 'goals.*', 'planner.*', 'today'), 'icon' => 'clubhouse'],
        ['label' => 'GoalLeagues', 'route' => 'leagues.index', 'active' => ! $onMatchups && request()->routeIs('leagues.*', 'challenges.*'), 'icon' => 'leagues', 'badge' => $unread],
        ['label' => 'Matchups', 'route' => 'matchups', 'active' => $onMatchups, 'icon' => 'matchups'],
        ['label' => 'Highlights', 'route' => 'highlights.index', 'active' => request()->routeIs('highlights.*', 'people.*', 'friends.*'), 'icon' => 'highlights'],
        ['label' => 'My Locker', 'route' => 'profile.show', 'active' => request()->routeIs('profile.*') || (request()->routeIs('profiles.show') && request()->route('user')?->is($viewer)), 'icon' => null],
    ];
    // Back: none on the five main tabs; otherwise the page's own parent (x-page-header back) or BackNavigation's.
    $backNav = in_array(request()->route()?->getName(), App\BackNavigation::TOP_LEVEL, true)
        ? null
        : (request()->attributes->get('page_back') ?? App\BackNavigation::parent(request()));
    $screenTitle = App\BackNavigation::title($title);
    $activeTab = collect($tabs)->firstWhere('active', true);
    $crumbs = $backNav ? App\BackNavigation::crumbs($activeTab['label'] ?? null, isset($activeTab['route']) ? route($activeTab['route']) : null, $backNav, $screenTitle) : [];
    // The floating "Check in" button: on every main screen except where today's list is already the page.
    $showCheckIn = ! request()->routeIs('today', 'goals.create', 'goals.intake*', 'welcome.*', 'cards.share');
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head')
</head>
<body class="min-h-dvh bg-ivory pr-[env(safe-area-inset-right)] pb-[calc(8.5rem+env(safe-area-inset-bottom))] pl-[env(safe-area-inset-left)] font-sans text-ink antialiased md:pb-16" data-signed-in data-density="compact">
    <x-splash />
    {{-- Slim top bar: stays put while the page scrolls, and stays the same element from page to page (Turbo keeps it;
         its tabs and badges are synced from each new page). --}}
    <div id="app-topbar" data-turbo-permanent class="sticky top-0 z-40 bg-club pt-[env(safe-area-inset-top)] [view-transition-name:app-topbar]">
        <header class="mx-auto flex h-13 max-w-5xl items-center justify-between gap-2 px-4 lg:gap-4">
            {{-- The left of the bar: the logo on the main tabs; elsewhere Back and the screen's title (the logo too on
                 desktop). Synced from each new page, since the bar itself stays put. --}}
            <div data-shell-sync="topbar-start" class="flex min-w-0 flex-1 items-center gap-1 md:flex-none">
                @if ($backNav)
                    <a href="{{ $backNav['url'] }}" data-back class="-ml-2.5 grid size-11 shrink-0 place-items-center rounded-full text-ivory hover:bg-ivory/10 focus-visible:outline-2 focus-visible:outline-mint" aria-label="Back to {{ $backNav['label'] }}">
                        <x-icon name="chevron-left" class="size-6" />
                    </a>
                    <span class="min-w-0 truncate text-[17px] font-bold text-ivory md:hidden" data-screen-title>{{ $screenTitle }}</span>
                    <a href="{{ route('dashboard') }}" class="hidden rounded-sm focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-mint md:block" aria-label="GoalLine, Clubhouse">
                        <x-brand.logo class="h-6" />
                    </a>
                @else
                    <a href="{{ route('dashboard') }}" class="rounded-sm focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-mint" aria-label="GoalLine, Clubhouse">
                        <x-brand.logo class="h-6" />
                    </a>
                @endif
            </div>

            {{-- Desktop tabs. --}}
            <nav class="hidden items-center md:flex lg:gap-1" aria-label="Main">
                @foreach ($tabs as $tab)
                    <a href="{{ route($tab['route']) }}" data-tab data-shell-sync="desktop-{{ $tab['route'] }}" @if ($tab['active']) aria-current="page" @endif @class([
                        'relative flex min-h-11 items-center rounded-full px-2 text-sm font-semibold whitespace-nowrap transition lg:px-4',
                        'bg-ivory/15 text-ivory' => $tab['active'],
                        'text-ivory/85 hover:bg-ivory/10 hover:text-ivory' => ! $tab['active'],
                    ])>
                        {{ $tab['label'] }}
                        @if (($tab['badge'] ?? 0) > 0)
                            <span class="absolute top-1.5 right-2 size-2 rounded-full bg-gold ring-2 ring-club"><span class="sr-only">{{ $tab['badge'] }} unread</span></span>
                        @endif
                    </a>
                @endforeach
            </nav>

            <div class="flex shrink-0 items-center gap-1 lg:gap-3">
                <a href="{{ route('goals.create') }}" class="hidden min-h-9 items-center gap-1.5 rounded-full bg-ivory px-3 text-[13px] font-semibold whitespace-nowrap text-club transition hover:bg-white md:flex" aria-label="Set a new goal">
                    <x-icon name="plus" class="size-4" /> <span class="hidden lg:inline">New goal</span>
                </a>
                {{-- Help: opens the Help sheet at the answer that fits this screen (help.js); a plain link without scripts. --}}
                <a href="{{ route('help') }}" data-open-help class="relative grid size-11 place-items-center rounded-full text-ivory/80 transition hover:bg-ivory/10 hover:text-ivory focus-visible:outline-2 focus-visible:outline-mint" aria-label="Help">
                    <x-icon name="help" class="size-6" />
                </a>
                <a href="{{ route('inbox') }}" data-shell-sync="inbox" @class(['relative grid size-11 place-items-center rounded-full transition hover:bg-ivory/10', 'text-ivory' => request()->routeIs('inbox'), 'text-ivory/80 hover:text-ivory' => ! request()->routeIs('inbox')])
                    aria-label="Inbox{{ $inboxCount ? ', '.($inboxCount > 9 ? '9+' : $inboxCount).' new' : '' }}">
                    <x-icon name="bell" class="size-6" />
                    @if ($inboxCount)
                        <span class="absolute top-0.5 right-0.5 flex h-4.5 min-w-4.5 items-center justify-center rounded-full bg-gold px-1 text-[11px] font-bold text-ink tabular-nums">{{ $inboxCount > 9 ? '9+' : $inboxCount }}</span>
                    @endif
                </a>

                {{-- Account menu: your locker, settings, admin, and log out, under your photo. --}}
                <details class="relative" data-account-menu>
                    <summary class="grid size-11 cursor-pointer list-none place-items-center rounded-full [&::-webkit-details-marker]:hidden">
                        <span class="sr-only">Account menu</span>
                        <x-avatar :user="$viewer" tone="light" class="size-8 text-[11px] ring-2 ring-ivory/25 transition hover:ring-ivory/60" />
                    </summary>
                    <div class="absolute right-0 z-50 mt-2 w-56 overflow-hidden rounded-2xl border border-line bg-white py-1.5 text-sm shadow-xl">
                        <p class="truncate border-b border-line px-4 pt-1.5 pb-2.5">
                            <span class="block font-semibold text-ink">{{ $viewer->name }}</span>
                            <span class="block text-xs text-ink/70">{{ '@'.$viewer->username }}</span>
                        </p>
                        <a href="{{ route('profile.show') }}" class="block px-4 py-2 text-ink hover:bg-mint">My Locker</a>
                        <a href="{{ route('profile.edit') }}" class="block px-4 py-2 text-ink hover:bg-mint">Profile settings</a>
                        <a href="{{ route('settings.notifications') }}" class="block px-4 py-2 text-ink hover:bg-mint">Notifications &amp; calendar</a>
                        <a href="{{ route('recap.week') }}" class="block px-4 py-2 text-ink hover:bg-mint">Your week</a>
                        <a href="{{ route('about') }}" class="block px-4 py-2 text-ink hover:bg-mint">About GoalLine</a>
                        <a href="{{ route('about') }}#contact" class="block px-4 py-2 text-ink hover:bg-mint">Send feedback</a>
                        @can('admin')
                            <a href="{{ route('admin.index') }}" class="block px-4 py-2 font-semibold text-ink hover:bg-gold/20"><span class="mr-1.5 inline-block size-2 rounded-full bg-gold align-middle"></span>Admin</a>
                        @endcan
                        <form method="POST" action="{{ route('logout') }}" class="mt-1 border-t border-line pt-1">
                            @csrf
                            <button type="submit" class="block w-full px-4 py-2 text-left text-ink/70 hover:bg-ivory hover:text-ink">Log out</button>
                        </form>
                    </div>
                </details>
            </div>
        </header>
    </div>

    <section class="bg-club">
        @if ($crumbs !== [])
            {{-- Desktop: where you are, as a short trail. --}}
            <nav aria-label="Breadcrumb" class="mx-auto hidden max-w-5xl px-4 pt-2 md:block" data-breadcrumbs>
                <ol class="flex items-center gap-1.5 text-xs text-ivory/85">
                    @foreach ($crumbs as $crumb)
                        <li class="flex min-w-0 items-center gap-1.5">
                            @if (! $loop->first)<span aria-hidden="true" class="text-ivory/60">/</span>@endif
                            @if ($crumb['url'])
                                <a href="{{ $crumb['url'] }}" class="truncate hover:text-ivory hover:underline">{{ $crumb['label'] }}</a>
                            @else
                                <span class="truncate font-semibold text-ivory" aria-current="page">{{ $crumb['label'] }}</span>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </nav>
        @endif
        <div class="gl-hero">{{ $hero ?? '' }}</div>
    </section>

    <x-goal-line />

    {{-- Shown over the page while a slow next page loads (after a short wait), so a tap always shows progress. If the
         page can't be reached or takes far too long, it turns into a friendly "Try again". --}}
    <div id="nav-skeleton" data-nav-skeleton data-turbo-permanent hidden class="fixed inset-x-0 top-[calc(3.25rem+env(safe-area-inset-top))] bottom-0 z-30 bg-ivory/95 px-4 pt-4">
        <div class="mx-auto max-w-5xl">
            <div data-nav-loading>
                <x-line-loader size="inline" label="Loading the page" :delayed="false" class="mb-3" />
                <x-skeleton :rows="6" />
            </div>
            <x-load-error data-nav-error hidden />
        </div>
    </div>

    <main class="mx-auto max-w-5xl px-4 pt-4 pb-8 md:pt-6" data-help-context="{{ App\Http\Controllers\HelpController::CONTEXTS[request()->route()?->getName()] ?? '' }}"
        @if ($viewer->onboarded_at && $viewer->tour_completed_at === null) data-tour-pending @endif>
        @if ($viewer->isDemo())
            {{-- One slim line, so the page starts right under it. --}}
            <div class="mb-3 flex items-center justify-between gap-2 rounded-xl border border-club/25 bg-mint px-3 py-1.5 text-[13px]">
                <p class="min-w-0 truncate text-ink" title="Everyone here is pretend, so click anything. The coach uses saved sample answers, and this demo is deleted after {{ config('goalline.demo.ttl_hours') }} hours.">
                    <span class="eyebrow mr-1 rounded-full bg-club px-1.5 py-0.5 text-[11px] text-ivory">Demo mode</span> You're {{ Str::before($viewer->name, ' ') }}. All pretend, so tap anything.
                </p>
                <div class="flex shrink-0 items-center gap-3">
                    {{-- A normal form post: rebuilding the world can outlast the in-page loader's time limit. --}}
                    <form method="POST" action="{{ route('demo.reset') }}" data-live="false">
                        @csrf
                        <button type="submit" class="gl-tap relative font-semibold text-club hover:underline">Reset demo</button>
                    </form>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="gl-tap relative font-semibold text-club hover:underline" aria-label="Leave the demo">Leave</button>
                    </form>
                </div>
            </div>
        @endif
        @unless ($viewer->hasVerifiedEmail() || request()->routeIs('verification.notice'))
            <div class="mb-3 flex flex-col gap-1 rounded-xl border border-gold/50 bg-gold/15 px-3 py-2 text-[13px] sm:flex-row sm:items-center sm:justify-between">
                <p class="text-ink"><span class="font-semibold">Verify your email</span> to unlock GoalLeagues, Highlights, and friends. We sent a link to {{ $viewer->email }}.</p>
                <form method="POST" action="{{ route('verification.send') }}" class="shrink-0">
                    @csrf
                    <button type="submit" class="font-semibold text-club hover:underline">Resend link</button>
                </form>
            </div>
        @endunless

        @if (session('status'))
            <p role="status" data-flash class="mb-3 flex items-center gap-2.5 rounded-xl border border-grass/30 bg-mint px-3 py-2 text-[13px] font-medium text-club">
                <span class="size-2 rounded-full bg-grass" aria-hidden="true"></span>
                {{ session('status') }}
            </p>
        @endif

        @if (session('band_up'))
            {{-- A check-in just moved a GoalWeek up a band (worked out by the scoring code): celebrate.js plays it. --}}
            <span data-band-up hidden data-band="{{ session('band_up')['band'] }}" data-label="{{ session('band_up')['label'] }}"
                data-percent="{{ session('band_up')['percent'] }}" data-needs-proof="{{ session('band_up')['needsProof'] ? '1' : '' }}"
                data-goal-url="{{ session('band_up')['goalUrl'] }}"></span>
        @endif

        {{ $slot }}
    </main>

    <footer class="border-t border-line">
        <nav class="mx-auto flex max-w-5xl flex-wrap items-center gap-x-5 gap-y-2 px-4 py-5 text-sm text-ink/70" aria-label="Footer">
            <a href="{{ route('about') }}" class="hover:text-club">About</a>
            <a href="{{ route('privacy') }}" class="hover:text-club">Privacy</a>
            <a href="{{ route('terms') }}" class="hover:text-club">Terms</a>
        </nav>
    </footer>

    {{-- Phone tab bar: always there, above the iPhone home indicator, so no screen is a dead end. It's fixed, on its
         own compositor layer, outside every scrolling area, and the same element from page to page (Turbo keeps it;
         the active tab and badges are synced from each new page), so it never moves, flickers or changes height. --}}
    <nav id="app-tabbar" data-turbo-permanent class="gl-tabbar fixed inset-x-0 bottom-0 z-40 border-t border-line bg-white/95 pb-[env(safe-area-inset-bottom)] backdrop-blur md:hidden" aria-label="Main">
        <ul class="mx-auto grid max-w-md grid-cols-5">
            @foreach ($tabs as $tab)
                <li>
                    <a href="{{ route($tab['route']) }}" data-tab data-shell-sync="tab-{{ $tab['route'] }}" @if ($tab['active']) aria-current="page" @endif @class([
                        'relative flex min-h-13 flex-col items-center justify-center gap-0.5 pt-1.5 pb-1 text-[11px] font-semibold transition',
                        'text-club' => $tab['active'],
                        'text-ink/70 hover:text-ink' => ! $tab['active'],
                    ])>
                        @if ($tab['icon'])
                            <x-icon :name="$tab['icon']" class="size-[22px]" />
                        @else
                            <x-avatar :user="$viewer" class="size-[22px] text-[9px] {{ $tab['active'] ? 'ring-2 ring-club ring-offset-1' : '' }}" />
                        @endif
                        {{ $tab['label'] }}
                        @if (($tab['badge'] ?? 0) > 0)
                            <span class="absolute top-1.5 left-1/2 ml-2 min-w-4 rounded-full bg-gold px-1 text-center text-[11px] leading-4 text-ink">{{ min($tab['badge'], 99) }}</span>
                        @endif
                        @if ($tab['active'])
                            <span class="absolute top-0 h-0.5 w-8 rounded-full bg-club" aria-hidden="true"></span>
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>

    <x-band-celebration />

    @if ($showCheckIn)
        {{-- Check in from anywhere: a floating button that opens today's list as a bottom sheet over the page. --}}
        <button type="button" data-checkin-open
            class="fixed right-[max(1rem,env(safe-area-inset-right))] bottom-[calc(4.5rem+env(safe-area-inset-bottom))] z-30 inline-flex min-h-12 items-center gap-2 rounded-full bg-club pr-4 pl-3.5 text-sm font-semibold text-ivory shadow-lg shadow-club/25 transition hover:bg-club-dark active:scale-95 md:bottom-6">
            <x-icon name="check-in" class="size-5" /> Check in
        </button>
        <dialog data-checkin-sheet data-live-source="{{ route('checkin.sheet') }}" aria-label="Check in for today"
            class="gl-sheet m-0 mt-auto w-full max-w-none rounded-t-2xl bg-ivory p-0 text-ink shadow-2xl backdrop:bg-ink/40 sm:m-auto sm:max-w-lg sm:rounded-2xl">
            <div class="flex items-center justify-between gap-3 border-b border-line bg-white px-4 py-2">
                <p class="font-serif text-base font-bold tracking-tight text-club">Check in</p>
                <a href="{{ route('today') }}" class="ml-auto text-[13px] font-semibold text-club hover:underline">Open Today</a>
                <form method="dialog">
                    <button type="submit" class="grid size-11 place-items-center rounded-full text-ink/70 hover:bg-ivory" aria-label="Close"><x-icon name="x" class="size-5" /></button>
                </form>
            </div>
            <div data-checkin-body data-sheet-scroll class="max-h-[75dvh] overflow-y-auto p-3 pb-[max(1rem,env(safe-area-inset-bottom))]">
                <x-line-loader size="inline" label="Loading today’s list" class="mb-2" />
                <x-skeleton :rows="4" />
            </div>
            {{-- What the sheet shows while loading (again), and if today's list can't be reached. --}}
            <template data-checkin-loading>
                <x-line-loader size="inline" label="Loading today’s list" class="mb-2" />
                <x-skeleton :rows="4" />
            </template>
            <template data-checkin-error>
                <x-load-error message="Today’s list didn’t load. Check your connection, then give it another go." />
            </template>
        </dialog>
    @endif

    {{-- Sheets a page asked for (for example the scoring sheet behind "How is GoalLine scored?"). --}}
    @stack('sheets')

    @auth
        <x-kickoff-sheet />
    @endauth

    {{-- Help, from the ? in the top bar: filled with the Help Center when opened (help.js). --}}
    <dialog id="help-sheet" data-help-sheet aria-labelledby="help-sheet-title"
        class="gl-sheet m-0 mt-auto w-full max-w-none rounded-t-2xl bg-ivory p-0 text-ink shadow-2xl backdrop:bg-ink/40 sm:m-auto sm:max-w-lg sm:rounded-2xl">
        <div class="flex items-center justify-between gap-3 border-b border-line bg-white px-4 py-1.5">
            <h2 id="help-sheet-title" class="text-[18px] font-bold text-club">Help</h2>
            <form method="dialog">
                <button type="submit" class="grid size-11 place-items-center rounded-full text-ink/70 hover:bg-ivory" aria-label="Close"><x-icon name="x" class="size-5" /></button>
            </form>
        </div>
        <div data-help-body data-sheet-scroll class="max-h-[78dvh] overflow-y-auto px-4 pt-3 pb-[max(1.25rem,env(safe-area-inset-bottom))]">
            <x-line-loader size="inline" label="Loading help" />
        </div>
    </dialog>

    {{-- The first-run tour's coach mark (tour.js). --}}
    <div data-tour hidden role="dialog" aria-modal="false" aria-labelledby="tour-title"
        class="fixed z-[60] w-[min(20rem,calc(100vw-2rem))] rounded-2xl bg-white p-4 text-ink shadow-2xl ring-1 ring-line">
        <p class="text-[11px] font-semibold text-ink/70" data-tour-count></p>
        <h2 id="tour-title" class="mt-0.5 text-[16px] font-bold text-club" data-tour-title tabindex="-1"></h2>
        <p class="mt-1 text-[14px] text-ink/80" data-tour-text></p>
        <div class="mt-3 flex items-center justify-between gap-2">
            <button type="button" data-tour-skip class="min-h-11 rounded-full px-3 text-[13px] font-semibold text-ink/70 hover:text-ink">Skip tour</button>
            <span class="flex gap-2">
                <button type="button" data-tour-back class="min-h-11 rounded-full border border-line px-4 text-[13px] font-semibold text-club hover:border-club/40">Back</button>
                <button type="button" data-tour-next class="min-h-11 rounded-full bg-club px-4 text-[13px] font-semibold text-ivory hover:bg-club-dark">Next</button>
            </span>
        </div>
    </div>
</body>
</html>
