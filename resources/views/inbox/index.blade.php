<x-layouts.app title="Inbox · GoalLine">
    <x-slot:hero>
        <div class="mx-auto max-w-5xl px-4 pt-6 pb-12">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 class="font-serif text-4xl font-medium tracking-tight text-ivory sm:text-5xl">Inbox</h1>
                    <p class="mt-3 max-w-xl text-ivory/85">Cheers, follows, your friends&rsquo; highlights, GoalLeague news, and your results.</p>
                </div>
                <a href="{{ route('settings.notifications') }}" class="rounded-full border border-ivory/60 px-4 py-1.5 text-sm font-semibold text-ivory transition hover:border-ivory hover:bg-ivory hover:text-club">Settings</a>
            </div>
        </div>
    </x-slot:hero>

    <div class="mx-auto max-w-2xl">
        @if ($notifications->isEmpty())
            <section class="rounded-2xl border border-line bg-white px-6 py-10 text-center">
                <x-icon name="bell" class="mx-auto size-8 text-club/50" />
                <h2 class="mt-3 font-serif text-2xl font-medium text-club">All quiet</h2>
                <p class="mx-auto mt-2 max-w-md text-ink/70">When someone cheers you on, follows you, or joins your GoalLeague, you&rsquo;ll hear about it here.</p>
                <a href="{{ route('people.index') }}" class="mt-5 inline-block rounded-full bg-club px-5 py-2 text-sm font-semibold text-ivory hover:bg-club-dark">Find friends</a>
            </section>
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
                        <a href="{{ $notification->data['url'] ?? route('dashboard') }}" @class(['flex items-start gap-3 px-5 py-4 transition hover:bg-mint/40', 'bg-mint/60' => $isNew])>
                            @if ($actor)
                                <x-avatar :user="$actor" class="size-10 text-xs" />
                            @else
                                <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-club text-ivory">
                                    <x-icon :name="$icon" class="size-5" />
                                </span>
                            @endif
                            <span class="min-w-0 flex-1">
                                <span class="block text-ink">{{ $notification->data['text'] ?? '' }}</span>
                                <span class="mt-0.5 block text-xs text-ink/50">{{ App\Clock::shortTime($notification->created_at) }}</span>
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
