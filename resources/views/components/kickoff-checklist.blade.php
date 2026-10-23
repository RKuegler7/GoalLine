{{-- A new member's setup steps on the Clubhouse, checked off from what they've actually done. --}}
@props(['kickoff'])

@php
    $steps = $kickoff->steps();
    $done = collect($steps)->where('done', true)->count();
    $next = collect($steps)->firstWhere('done', false);
@endphp

<section {{ $attributes->class('overflow-hidden rounded-2xl border border-club/30 bg-white') }} aria-label="Kickoff checklist">
    <header class="flex flex-wrap items-center justify-between gap-3 border-b border-line bg-mint/50 px-5 py-4">
        <div>
            <h2 class="font-serif text-xl font-medium text-club">Your kickoff</h2>
            <p class="text-sm text-ink/60">{{ $done }} of {{ count($steps) }} done. Finish these and GoalLine works for you every day.</p>
        </div>
        <form method="POST" action="{{ route('kickoff.dismiss') }}">
            @csrf
            <button type="submit" class="text-sm font-medium text-ink/50 hover:text-ink">Hide</button>
        </form>
    </header>
    <div class="px-5 pt-3">
        <x-goal-progress :percent="$done / count($steps) * 100" label="Kickoff steps done" />
    </div>
    <ol class="grid gap-x-6 px-5 py-3 sm:grid-cols-2">
        @foreach ($steps as $step)
            <li>
                <a href="{{ $step['url'] }}" @class([
                    'flex items-start gap-3 rounded-xl px-2 py-2 transition',
                    'hover:bg-mint/50' => ! $step['done'],
                    'pointer-events-none' => $step['done'],
                    'bg-gold/15 ring-1 ring-gold/50' => $next && $step['key'] === $next['key'],
                ])>
                    <span @class([
                        'mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full border-2',
                        'border-grass bg-grass text-white' => $step['done'],
                        'border-club/40' => ! $step['done'],
                    ])>
                        @if ($step['done'])
                            <svg viewBox="0 0 16 16" class="size-3.5" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M3.5 8.5l3 3 6-7" /></svg>
                        @endif
                    </span>
                    <span class="min-w-0">
                        <span @class(['block text-sm font-semibold', 'text-ink/45 line-through decoration-ink/30' => $step['done'], 'text-ink' => ! $step['done']])>
                            {{ $step['label'] }}
                            @if ($step['done'])
                                <span class="sr-only">(done)</span>
                            @endif
                        </span>
                        @unless ($step['done'])
                            <span class="block text-xs text-ink/55">{{ $step['hint'] }}</span>
                        @endunless
                    </span>
                </a>
            </li>
        @endforeach
    </ol>
</section>
