{{-- One highlight in the feed or on a locker. --}}
@props(['highlight'])

@php
    $viewer = auth()->user();
    $mine = $highlight->user_id === $viewer->id;
    $cheered = $highlight->isCheeredBy($viewer);
@endphp

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
        @if ($mine)
            <details class="relative shrink-0">
                <summary class="cursor-pointer list-none rounded-full px-2 text-xl leading-none text-ink/40 hover:text-ink [&::-webkit-details-marker]:hidden" aria-label="Highlight options">&hellip;</summary>
                <div class="absolute right-0 z-10 mt-1 w-48 overflow-hidden rounded-xl border border-line bg-white py-1 text-sm shadow-lg">
                    <a href="{{ route('highlights.card', $highlight) }}" class="block px-4 py-2 text-ink hover:bg-mint">Get share card</a>
                    <form method="POST" action="{{ route('highlights.destroy', $highlight) }}" onsubmit="return confirm('Remove this highlight?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="block w-full px-4 py-2 text-left text-red-700 hover:bg-red-50">Remove</button>
                    </form>
                </div>
            </details>
        @endif
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
                    {{ $cheered ? 'Cheered' : 'Cheer' }}
                </button>
            </form>
            @if ($summary = $highlight->cheerSummary($viewer))
                <p class="truncate text-sm text-ink/55">{{ $summary }}</p>
            @endif
        </div>
    </div>
</article>
