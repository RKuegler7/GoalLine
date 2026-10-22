{{-- One comment under a highlight. Its author, or the highlight's, can remove it. --}}
@props(['comment', 'highlight'])

<li id="comment-{{ $comment->id }}" class="group flex items-start gap-2.5">
    <a href="{{ $comment->user->profileUrl() }}" class="shrink-0"><x-avatar :user="$comment->user" class="size-7 text-[0.6rem]" /></a>
    <div class="min-w-0 flex-1 rounded-xl bg-ivory px-3 py-2">
        <p class="text-sm">
            <a href="{{ $comment->user->profileUrl() }}" class="font-semibold text-ink hover:text-club hover:underline">{{ $comment->user->name }}</a>
            <span class="text-xs text-ink/45">&middot; {{ App\Clock::shortTime($comment->created_at) }}</span>
        </p>
        <p class="break-words whitespace-pre-line text-sm text-ink/85">{{ App\Mentions::render($comment->body) }}</p>
    </div>
    @if (in_array(auth()->id(), [$comment->user_id, $highlight->user_id], true))
        <form method="POST" action="{{ route('highlights.comments.destroy', $comment) }}" class="shrink-0 opacity-0 transition group-hover:opacity-100 group-focus-within:opacity-100"
            onsubmit="return confirm('Delete this comment?')">
            @csrf
            @method('DELETE')
            <button type="submit" class="rounded px-1.5 text-sm text-ink/40 hover:text-red-700" title="Delete comment">&times;<span class="sr-only">Delete comment</span></button>
        </form>
    @endif
</li>
