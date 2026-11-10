<x-layouts.app title="Inbox · GoalLine">
    <x-slot:hero>
        <x-page-header title="Inbox" subtitle="Cheers, friend requests, league news and your results.">
            <a href="{{ route('settings.notifications') }}" class="gl-tap relative inline-flex min-h-9 items-center rounded-full bg-ivory/15 px-3 text-[13px] font-semibold text-ivory hover:bg-ivory/25">Settings</a>
        </x-page-header>
    </x-slot:hero>

    <div class="mx-auto max-w-2xl">
        @if ($notifications->isEmpty())
            <x-empty-state title="All quiet" message="When someone cheers you on, sends a friend request, or joins your GoalLeague, you’ll hear about it here." action="Add friends" :href="route('friends.index')" />
        @else
            <ul class="divide-y divide-line overflow-hidden rounded-2xl border border-line bg-white">
                @foreach ($notifications as $notification)
                    @php
                        $actor = $actors->get($notification->data['actor_id'] ?? null);
                        $isNew = $unreadIds->contains($notification->id);
                        $icon = match ($notification->data['category'] ?? null) {
                            'results', 'leagues' => 'leagues',
                            'news' => 'highlights',
                            default => 'bell',
                        };
                    @endphp
                    <li>
                        <a href="{{ $notification->data['url'] ?? route('dashboard') }}" @class(['flex items-start gap-3 px-4 py-3 transition hover:bg-mint/40', 'bg-mint/60' => $isNew])>
                            @if ($actor)
                                <x-avatar :user="$actor" class="size-10 text-xs" />
                            @else
                                <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-club text-ivory">
                                    <x-icon :name="$icon" class="size-5" />
                                </span>
                            @endif
                            <span class="min-w-0 flex-1">
                                <span class="block text-ink">{{ $notification->data['text'] ?? '' }}</span>
                                <span class="mt-0.5 block text-xs text-ink/70">{{ App\Clock::shortTime($notification->created_at) }}</span>
                            </span>
                            @if ($isNew)
                                <span class="mt-2 size-2.5 shrink-0 rounded-full bg-grass" title="New"><span class="sr-only">New</span></span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>

            <div class="mt-6">{{ $notifications->links() }}</div>
        @endif
    </div>
</x-layouts.app>
