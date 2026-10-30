@props(['title' => 'GoalLine'])

@php
    $viewer = auth()->user();
    $unread = $viewer->unreadLeagueMessages();
    $inboxCount = $viewer->unreadNotifications()->count();
    $tabs = [
        ['label' => 'Clubhouse', 'route' => 'dashboard', 'active' => request()->routeIs('dashboard', 'goals.*', 'planner.*'), 'icon' => 'clubhouse'],
        ['label' => 'GoalLeagues', 'route' => 'leagues.index', 'active' => request()->routeIs('leagues.*', 'challenges.*'), 'icon' => 'leagues', 'badge' => $unread],
        ['label' => 'Highlights', 'route' => 'highlights.index', 'active' => request()->routeIs('highlights.*', 'people.*'), 'icon' => 'highlights'],
        ['label' => 'My Locker', 'route' => 'profile.show', 'active' => request()->routeIs('profile.*') || (request()->routeIs('profiles.show') && request()->route('user')?->is($viewer)), 'icon' => null],
    ];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @include('partials.head')
</head>
<body class="min-h-screen bg-ivory pb-24 font-sans text-ink antialiased md:pb-0" data-signed-in>
    <section class="bg-club pt-[env(safe-area-inset-top)]">
        <header class="mx-auto flex max-w-5xl items-center justify-between gap-4 px-4 py-4">
            <a href="{{ route('dashboard') }}" class="rounded-sm focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-mint">
                <x-brand.logo class="h-7 sm:h-8" />
            </a>

            {{-- Desktop tabs. --}}
            <nav class="hidden items-center gap-1 md:flex" aria-label="Main">
                @foreach ($tabs as $tab)
                    <a href="{{ route($tab['route']) }}" @if ($tab['active']) aria-current="page" @endif @class([
                        'relative rounded-full px-3 py-2 text-sm font-semibold whitespace-nowrap transition lg:px-4',
                        'bg-ivory/15 text-ivory' => $tab['active'],
                        'text-ivory/70 hover:bg-ivory/10 hover:text-ivory' => ! $tab['active'],
                    ])>
                        {{ $tab['label'] }}
                        @if (($tab['badge'] ?? 0) > 0)
                            <span class="absolute top-1.5 right-2 size-2 rounded-full bg-gold ring-2 ring-club"><span class="sr-only">{{ $tab['badge'] }} unread</span></span>
                        @endif
                    </a>
                @endforeach
            </nav>

            <div class="flex shrink-0 items-center gap-2 sm:gap-3">
                <a href="{{ route('goals.create') }}" class="hidden items-center gap-1.5 rounded-full bg-ivory px-4 py-2 text-sm font-semibold whitespace-nowrap text-club transition hover:bg-white md:flex" aria-label="New goal">
                    <x-icon name="plus" class="size-4" /> <span class="hidden lg:inline">New goal</span>
                </a>
                <a href="{{ route('inbox') }}" @class(['relative rounded-full p-1.5 transition hover:bg-ivory/10', 'text-ivory' => request()->routeIs('inbox'), 'text-ivory/80 hover:text-ivory' => ! request()->routeIs('inbox')])
                    aria-label="Inbox{{ $inboxCount ? ', '.$inboxCount.' new' : '' }}">
                    <x-icon name="bell" class="size-6" />
                    @if ($inboxCount)
                        <span class="absolute -top-0.5 -right-0.5 flex h-4.5 min-w-4.5 items-center justify-center rounded-full bg-gold px-1 text-[0.62rem] font-bold text-ink tabular-nums">{{ $inboxCount > 9 ? '9+' : $inboxCount }}</span>
                    @endif
                </a>

                {{-- Account menu: your locker, settings, admin, and log out, under your photo. --}}
                <details class="relative" data-account-menu>
                    <summary class="flex cursor-pointer list-none rounded-full ring-2 ring-ivory/25 transition hover:ring-ivory/60 [&::-webkit-details-marker]:hidden" aria-label="Account menu">
                        <x-avatar :user="$viewer" tone="light" class="size-9 text-xs" />
                    </summary>
                    <div class="absolute right-0 z-50 mt-2 w-56 overflow-hidden rounded-2xl border border-line bg-white py-1.5 text-sm shadow-xl">
                        <p class="truncate border-b border-line px-4 pt-1.5 pb-2.5">
                            <span class="block font-semibold text-ink">{{ $viewer->name }}</span>
                            <span class="block text-xs text-ink/50">{{ '@'.$viewer->username }}</span>
                        </p>
                        <a href="{{ route('profile.show') }}" class="block px-4 py-2 text-ink hover:bg-mint">My Locker</a>
                        <a href="{{ route('profile.edit') }}" class="block px-4 py-2 text-ink hover:bg-mint">Profile settings</a>
                        <a href="{{ route('settings.notifications') }}" class="block px-4 py-2 text-ink hover:bg-mint">Notifications &amp; calendar</a>
                        <a href="{{ route('recap.week') }}" class="block px-4 py-2 text-ink hover:bg-mint">Your week</a>
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

        {{ $hero ?? '' }}
    </section>

    <x-goal-line />

    <main class="mx-auto max-w-5xl px-4 py-10">
        @if ($viewer->isDemo())
            <div class="mb-6 flex flex-col gap-2 rounded-xl border border-club/30 bg-mint px-4 py-3 text-sm sm:flex-row sm:items-center sm:justify-between">
                <p class="text-ink"><span class="font-semibold text-club">You're in the GoalLine demo</span> as {{ $viewer->name }}. Your friends and leagues are pretend, so click anything. This demo is deleted in {{ config('goalline.demo.ttl_hours') }} hours.</p>
                <div class="flex shrink-0 items-center gap-4">
                    <a href="{{ route('demo') }}" class="font-semibold text-club hover:underline">Start over</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="font-semibold text-club hover:underline">Leave the demo</button>
                    </form>
                </div>
            </div>
        @endif
        @unless ($viewer->hasVerifiedEmail() || request()->routeIs('verification.notice'))
            <div class="mb-6 flex flex-col gap-2 rounded-xl border border-gold/50 bg-gold/15 px-4 py-3 text-sm sm:flex-row sm:items-center sm:justify-between">
                <p class="text-ink"><span class="font-semibold">Verify your email</span> to unlock GoalLeagues, Highlights, and friends. We sent a link to {{ $viewer->email }}.</p>
                <form method="POST" action="{{ route('verification.send') }}" class="shrink-0">
                    @csrf
                    <button type="submit" class="font-semibold text-club hover:underline">Resend link</button>
                </form>
            </div>
        @endunless

        @if (session('status'))
            <p role="status" class="mb-6 flex items-center gap-3 rounded-xl border border-grass/30 bg-mint px-4 py-3 text-sm font-medium text-club">
                <span class="size-2 rounded-full bg-grass" aria-hidden="true"></span>
                {{ session('status') }}
            </p>
        @endif

        {{ $slot }}
    </main>

    {{-- Phone tab bar, Instagram-style, with "set a goal" in the middle. --}}
    <nav class="fixed inset-x-0 bottom-0 z-40 border-t border-line bg-white/95 pb-[env(safe-area-inset-bottom)] backdrop-blur md:hidden" aria-label="Main">
        <ul class="mx-auto grid max-w-md grid-cols-5 items-end">
            @foreach ($tabs as $index => $tab)
                @if ($index === 2)
                    <li class="flex justify-center">
                        <a href="{{ route('goals.create') }}" class="-mt-5 mb-2 flex size-14 items-center justify-center rounded-full bg-club text-ivory shadow-lg shadow-club/30 ring-4 ring-ivory transition active:scale-95" aria-label="Set a new goal">
                            <x-icon name="plus" class="size-6" />
                        </a>
                    </li>
                @endif
                <li>
                    <a href="{{ route($tab['route']) }}" @if ($tab['active']) aria-current="page" @endif @class([
                        'relative flex flex-col items-center gap-1 pt-2.5 pb-2 text-[0.68rem] font-semibold transition',
                        'text-club' => $tab['active'],
                        'text-ink/45 hover:text-ink/70' => ! $tab['active'],
                    ])>
                        @if ($tab['icon'])
                            <x-icon :name="$tab['icon']" class="size-6" />
                        @else
                            <x-avatar :user="$viewer" class="size-6 text-[0.55rem] {{ $tab['active'] ? 'ring-2 ring-club ring-offset-1' : '' }}" />
                        @endif
                        {{ $tab['label'] }}
                        @if (($tab['badge'] ?? 0) > 0)
                            <span class="absolute top-1.5 left-1/2 ml-2 min-w-4 rounded-full bg-gold px-1 text-center text-[0.6rem] leading-4 text-ink">{{ min($tab['badge'], 99) }}</span>
                        @endif
                        @if ($tab['active'])
                            <span class="absolute top-0 h-0.5 w-8 rounded-full bg-club" aria-hidden="true"></span>
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>
    @if (config('goalline.beta.invite_only'))
        <x-feedback-button />
    @endif
</body>
</html>
