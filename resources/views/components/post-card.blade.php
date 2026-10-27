{{-- A check-in post: the photo behind a checked-off task. Who's left, league and goal right, then the photo,
     the caption, and a footer with the task, the Verified check, where, when, and kudos. --}}
@props(['post'])

@php
    $viewer = auth()->user();
    $kudos = $post->cheers->count();
    $gaveKudos = $post->isCheeredBy($viewer);
@endphp

<article id="highlight-{{ $post->id }}" class="scroll-mt-24 overflow-hidden rounded-2xl border border-line bg-white">
    <header class="flex items-start gap-3 px-4 py-3">
        <a href="{{ $post->user->profileUrl() }}" class="flex min-w-0 flex-1 items-center gap-2.5">
            <x-avatar :user="$post->user" class="size-9 shrink-0 text-[0.65rem]" />
            <span class="truncate font-semibold text-ink hover:text-club">{{ $post->user->name }}</span>
        </a>
        <div class="min-w-0 max-w-[55%] text-right">
            @if ($post->league)
                <a href="{{ route('leagues.feed', $post->league) }}" class="eyebrow block truncate text-[0.6rem] text-club hover:underline">{{ $post->league->name }}</a>
            @else
                <span class="eyebrow block text-[0.6rem] text-ink/45">Friends</span>
            @endif
            <span class="block truncate text-sm text-ink/70">{{ $post->headline }}</span>
        </div>
        <x-post-options :post="$post" />
    </header>

    @if ($post->photoUrl())
        <img src="{{ $post->photoUrl() }}" alt="{{ $post->user->name }}'s photo for {{ $post->detail }}" class="max-h-[32rem] w-full bg-ink/5 object-cover" loading="lazy">
    @endif

    @if ($post->caption)
        <p class="px-4 pt-3 whitespace-pre-line text-ink/90">{{ $post->caption }}</p>
    @endif

    <footer class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-2 border-t border-line px-4 py-3 text-sm">
        <span class="flex min-w-0 items-center gap-1.5 text-ink/80">
            <svg viewBox="0 0 16 16" class="size-3.5 shrink-0 text-grass" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M3.5 8.5l3 3 6-7" /></svg>
            <span class="truncate">{{ $post->detail }}</span>
        </span>
        @if ($post->task?->isVerified())
            <span class="rounded-full bg-mint px-2 py-0.5 text-xs font-semibold text-club">Verified</span>
        @endif
        @if ($post->location)
            <span class="text-ink/55">&#x1F4CD; {{ $post->location }}</span>
        @endif
        <span class="text-ink/45">{{ App\Clock::shortTime($post->created_at) }}</span>

        <form method="POST" action="{{ route('highlights.cheer', $post) }}" class="ml-auto">
            @csrf
            <button type="submit" aria-pressed="{{ $gaveKudos ? 'true' : 'false' }}" title="{{ $post->cheerSummary($viewer) ?? 'Give kudos' }}" @class([
                'flex items-center gap-1.5 rounded-full px-3 py-1 text-sm font-semibold transition',
                'bg-grass text-white' => $gaveKudos,
                'bg-mint text-club hover:bg-grass/20' => ! $gaveKudos,
            ])>
                <x-icon name="highlights" class="size-4" />
                Kudos @if ($kudos > 0)<span class="tabular-nums">{{ $kudos }}</span>@endif
            </button>
        </form>
    </footer>
</article>
