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
        'text-ink/70 hover:text-ink' => $tone === 'dark',
        'text-ivory/80 hover:text-ivory' => $tone === 'light',
    ]) aria-label="Post options">&hellip;</summary>
    <div class="absolute right-0 z-10 mt-1 w-60 overflow-hidden rounded-xl border border-line bg-white py-1 text-sm shadow-lg">
        @if ($mine && ! $post->isPost() && ! in_array($post->kind, [App\HighlightKind::WeekCard, App\HighlightKind::SeasonCard, App\HighlightKind::GoalDay], true))
            <a href="{{ route('highlights.card', $post) }}" class="block px-4 py-2 text-ink hover:bg-mint">Get share card</a>
        @endif
        @if ($canRemove)
            <form method="POST" action="{{ route('highlights.destroy', $post) }}" onsubmit="return confirm('{{ $mine ? 'Remove this post?' : 'Remove this post from your GoalLeague?' }}')">
                @csrf
                @method('DELETE')
                <button type="submit" class="block w-full px-4 py-2 text-left text-red-700 hover:bg-red-50">{{ $mine ? 'Remove' : 'Remove from league' }}</button>
            </form>
        @endif
        {{-- Who sees it: switch any time, including taking a public post out of Explore. --}}
        @if ($mine && $post->visibilityChoices() !== [])
            <form method="POST" action="{{ route('highlights.visibility', $post) }}" class="border-t border-line px-4 py-2" data-post-visibility>
                @csrf
                @method('PATCH')
                <label for="visibility-{{ $post->id }}" class="block text-xs font-semibold text-ink/70">Who sees it</label>
                <select id="visibility-{{ $post->id }}" name="visibility" class="mt-1 w-full rounded-lg border border-line px-2 py-1 text-xs">
                    @foreach ($post->visibilityChoices() as $choice)
                        <option value="{{ $choice }}" @selected(($post->visibility?->value ?? 'circle') === $choice)>{{ $choice === 'circle' ? 'Friends and league mates' : App\PostVisibility::from($choice)->label((bool) $post->league_id) }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-[11px] text-ink/70">Public (Explore): anyone on GoalLine can see it.</p>
                <button type="submit" class="mt-1.5 text-xs font-semibold text-club hover:underline">Save</button>
            </form>
        @endif
        @unless ($mine)
            <form method="POST" action="{{ route('users.mute', $post->user_id) }}" class="border-t border-line">
                @csrf
                <button type="submit" class="block w-full px-4 py-2 text-left text-ink hover:bg-mint" data-mute>Mute {{ Str::before($post->user->name, ' ') }}</button>
            </form>
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
