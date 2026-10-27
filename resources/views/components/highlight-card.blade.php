{{-- One highlight in the feed or on a locker. --}}
@props(['highlight'])

@php
    $viewer = auth()->user();
    $mine = $highlight->user_id === $viewer->id;
    $cheered = $highlight->isCheeredBy($viewer);
    $comments = $highlight->comments;
    $hiddenComments = max(0, $comments->count() - 2);

    // Who you might @mention here: the author, everyone in the thread, and your friends.
    $mentionable = collect([$highlight->user])
        ->merge($comments->pluck('user'))
        ->merge(once(fn () => $viewer->friends()))
        ->filter()
        ->reject(fn ($person) => $person->is($viewer))
        ->unique('id')
        ->map(fn ($person) => ['u' => $person->username, 'n' => $person->name])
        ->values();
@endphp

@if ($highlight->isPost())
    <x-post-card :post="$highlight" />
@else
<article id="highlight-{{ $highlight->id }}" class="scroll-mt-24 overflow-hidden rounded-2xl border border-line bg-white">
    <header class="flex items-center gap-3 px-5 pt-4">
        <a href="{{ $highlight->user->profileUrl() }}" class="shrink-0"><x-avatar :user="$highlight->user" class="size-10 text-xs" /></a>
        <div class="min-w-0 flex-1">
            <p class="truncate">
                <a href="{{ $highlight->user->profileUrl() }}" class="font-semibold text-ink hover:text-club hover:underline">{{ $highlight->user->name }}</a>
                <span class="text-sm text-ink/45">&middot; {{ App\Clock::shortTime($highlight->created_at) }}</span>
            </p>
            <span @class([
                'eyebrow mt-0.5 inline-block rounded-full px-2 py-0.5 text-[0.6rem]',
                'bg-gold text-ink' => $highlight->kind === App\HighlightKind::GoalCompleted,
                'bg-grass text-white' => $highlight->kind === App\HighlightKind::GreatWeek,
                'bg-mint text-club' => $highlight->kind === App\HighlightKind::MilestoneHit,
            ])>{{ $highlight->kind->label() }}</span>
        </div>
        <x-post-options :post="$highlight" />
    </header>

    <div class="mt-4">
        @if ($highlight->photoUrl())
            <img src="{{ $highlight->photoUrl() }}" alt="Photo shared with {{ $highlight->headline }}" class="aspect-[4/3] w-full object-cover" loading="lazy">
        @else
            <div class="relative overflow-hidden bg-club px-5 py-8">
                <x-field-backdrop class="absolute inset-0 size-full" />
                <p class="relative font-serif text-2xl font-medium text-balance text-ivory sm:text-3xl">{{ $highlight->headline }}</p>
            </div>
            <x-goal-line />
        @endif
    </div>

    <div class="px-5 py-4">
        @if ($highlight->photoUrl())
            <h3 class="font-serif text-xl font-medium text-club">{{ $highlight->headline }}</h3>
        @endif
        @if ($highlight->detail)
            <p class="text-sm text-ink/60">{{ $highlight->detail }}</p>
        @endif
        @if ($highlight->caption)
            <p class="mt-2 whitespace-pre-line text-ink/90">{{ $highlight->caption }}</p>
        @endif

        <div class="mt-4 flex items-center gap-3 border-t border-line pt-3">
            <form method="POST" action="{{ route('highlights.cheer', $highlight) }}">
                @csrf
                <button type="submit" aria-pressed="{{ $cheered ? 'true' : 'false' }}" @class([
                    'flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-sm font-semibold transition',
                    'bg-grass text-white' => $cheered,
                    'bg-mint text-club hover:bg-grass/20' => ! $cheered,
                ])>
                    <x-icon name="highlights" class="size-4" />
                    {{ $cheered ? 'Kudos given' : 'Kudos' }}
                </button>
            </form>
            @if ($summary = $highlight->cheerSummary($viewer))
                <p class="truncate text-sm text-ink/55">{{ $summary }}</p>
            @endif
        </div>

        {{-- Comments: the latest two, with the rest a tap away. --}}
        @if ($comments->isNotEmpty())
            <div class="mt-3 space-y-2.5">
                @if ($hiddenComments > 0)
                    <details class="group/more">
                        <summary class="cursor-pointer list-none text-sm font-semibold text-ink/55 hover:text-club [&::-webkit-details-marker]:hidden">
                            <span class="group-open/more:hidden">View all {{ $comments->count() }} comments</span>
                            <span class="hidden group-open/more:inline">Show fewer</span>
                        </summary>
                        <ul class="mt-2.5 space-y-2.5">
                            @foreach ($comments->take($hiddenComments) as $comment)
                                <x-highlight-comment :comment="$comment" :highlight="$highlight" />
                            @endforeach
                        </ul>
                    </details>
                @endif
                <ul class="space-y-2.5">
                    @foreach ($comments->skip($hiddenComments) as $comment)
                        <x-highlight-comment :comment="$comment" :highlight="$highlight" />
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('highlights.comments.store', $highlight) }}" class="relative mt-3 flex items-end gap-2">
            @csrf
            <x-avatar :user="$viewer" class="mb-1 size-7 text-[0.6rem]" />
            <label for="comment-{{ $highlight->id }}" class="sr-only">Add a comment</label>
            <textarea id="comment-{{ $highlight->id }}" name="body" rows="1" maxlength="{{ App\Models\HighlightComment::MAX_LENGTH }}" required
                placeholder="Add a comment. Type @ to mention someone."
                data-mention-input data-mentions="{{ $mentionable->toJson() }}"
                class="max-h-32 min-h-[2.4rem] flex-1 resize-y rounded-xl border border-line bg-ivory/60 px-3 py-2 text-sm text-ink outline-none placeholder:text-ink/40 focus:border-club focus:bg-white focus:ring-4 focus:ring-club/10"></textarea>
            <button type="submit" class="rounded-xl px-3 py-2 text-sm font-semibold text-club hover:bg-mint">Post</button>
        </form>
    </div>
</article>
@endif
