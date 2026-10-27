{{-- A check-in post, laid out like a photo app: a header with who, the goal, the GoalLeague, and where; the photo;
     a row with Cheer and the Verified check; then who cheered, the caption, the task, and when. --}}
@props(['post'])

@php
    $viewer = auth()->user();
    $cheers = $post->cheers->count();
    $cheered = $post->isCheeredBy($viewer);
@endphp

<article id="highlight-{{ $post->id }}" class="scroll-mt-24 overflow-hidden rounded-2xl border border-line bg-white">
    <header class="flex items-center gap-3 px-4 py-3">
        <a href="{{ $post->user->profileUrl() }}" class="shrink-0 rounded-full p-0.5 ring-2 ring-grass/60">
            <x-avatar :user="$post->user" class="size-9 text-[0.65rem]" />
        </a>
        <div class="min-w-0 flex-1 leading-tight">
            <p class="truncate text-sm">
                <a href="{{ $post->user->profileUrl() }}" class="font-semibold text-ink hover:underline">{{ $post->user->name }}</a>
                @if ($post->league)
                    <span class="text-ink/40">&middot;</span>
                    <a href="{{ route('leagues.feed', $post->league) }}" class="font-medium text-club hover:underline">{{ $post->league->name }}</a>
                @endif
            </p>
            <p class="truncate text-xs text-ink/60">{{ $post->headline }}</p>
            @if ($post->location)
                <p class="truncate text-xs text-ink/50">&#x1F4CD; {{ $post->location }}</p>
            @endif
        </div>
        <x-post-options :post="$post" />
    </header>

    @if ($post->photoUrl())
        <img src="{{ $post->photoUrl() }}" alt="{{ $post->user->name }}'s photo for {{ $post->detail }}" class="aspect-square w-full bg-ink/5 object-cover" loading="lazy">
    @endif

    <div class="px-4 pt-3 pb-4 text-sm">
        <div class="flex items-center gap-2">
            <form method="POST" action="{{ route('highlights.cheer', $post) }}">
                @csrf
                <button type="submit" aria-pressed="{{ $cheered ? 'true' : 'false' }}" @class([
                    'flex items-center gap-1.5 rounded-full px-3 py-1 font-semibold transition',
                    'bg-grass text-white' => $cheered,
                    'bg-mint text-club hover:bg-grass/20' => ! $cheered,
                ])>
                    <x-icon name="highlights" class="size-4" />
                    {{ $cheered ? 'Cheered' : 'Cheer' }}
                </button>
            </form>
            @if ($post->task?->isVerified())
                <span class="inline-flex items-center gap-1 rounded-full bg-mint px-2.5 py-1 text-xs font-semibold text-club">
                    <svg viewBox="0 0 16 16" class="size-3" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M3.5 8.5l3 3 6-7" /></svg>
                    Verified
                </span>
            @endif
        </div>

        @if ($cheers > 0)
            <p class="mt-2 font-semibold text-ink" title="{{ $post->cheerSummary($viewer) }}">{{ $cheers }} {{ Str::plural('cheer', $cheers) }}</p>
        @endif

        @if ($post->caption)
            <p class="mt-1.5 whitespace-pre-line text-ink/90"><a href="{{ $post->user->profileUrl() }}" class="font-semibold text-ink hover:underline">{{ $post->user->username }}</a> {{ $post->caption }}</p>
        @endif

        <p class="mt-1.5 flex items-center gap-1.5 text-ink/65">
            <svg viewBox="0 0 16 16" class="size-3.5 shrink-0 text-grass" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M3.5 8.5l3 3 6-7" /></svg>
            <span class="truncate">{{ $post->detail }}</span>
        </p>
        <p class="eyebrow mt-1.5 text-[0.6rem] text-ink/40">{{ App\Clock::shortTime($post->created_at) }}</p>
    </div>
</article>
