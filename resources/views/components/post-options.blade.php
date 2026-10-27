{{-- The "…" menu on a highlight or post: share card and remove for the author, remove for the GoalLeague owner,
     and report for everyone else. --}}
@props(['post', 'tone' => 'dark'])

@php
    $viewer = auth()->user();
    $mine = $post->user_id === $viewer->id;
    $canRemove = $mine || ($post->league_id && $post->league?->commissioner_id === $viewer->id);
@endphp

<details class="relative shrink-0">
    <summary @class([
        'cursor-pointer list-none rounded-full px-2 text-xl leading-none [&::-webkit-details-marker]:hidden',
        'text-ink/40 hover:text-ink' => $tone === 'dark',
        'text-ivory/80 hover:text-ivory' => $tone === 'light',
    ]) aria-label="Post options">&hellip;</summary>
    <div class="absolute right-0 z-10 mt-1 w-60 overflow-hidden rounded-xl border border-line bg-white py-1 text-sm shadow-lg">
        @if ($mine && ! $post->isPost())
            <a href="{{ route('highlights.card', $post) }}" class="block px-4 py-2 text-ink hover:bg-mint">Get share card</a>
        @endif
        @if ($canRemove)
            <form method="POST" action="{{ route('highlights.destroy', $post) }}" onsubmit="return confirm('{{ $mine ? 'Remove this post?' : 'Remove this post from your GoalLeague?' }}')">
                @csrf
                @method('DELETE')
                <button type="submit" class="block w-full px-4 py-2 text-left text-red-700 hover:bg-red-50">{{ $mine ? 'Remove' : 'Remove from league' }}</button>
            </form>
        @endif
        @unless ($mine)
            <form method="POST" action="{{ route('highlights.report', $post) }}" class="border-t border-line px-4 py-2">
                @csrf
                <label for="report-reason-{{ $post->id }}" class="block text-xs font-semibold text-ink/70">Report this post</label>
                <input id="report-reason-{{ $post->id }}" name="reason" maxlength="{{ App\Models\PostReport::MAX_REASON }}" placeholder="What's wrong? (optional)"
                    class="mt-1 w-full rounded-lg border border-line px-2 py-1 text-xs outline-none focus:border-club">
                <button type="submit" class="mt-1.5 text-xs font-semibold text-red-700 hover:underline">Report and hide it</button>
            </form>
        @endunless
    </div>
</details>
