@props(['goal' => null, 'collapsed' => false])

{{-- Who did what: what the AI coach drafted, and what code enforced. With a goal, the lines are live examples from it. --}}
@php
    $split = App\Services\AiCodeSplit::for($goal);
@endphp

<section {{ $attributes->class(['rounded-xl border border-line bg-white']) }} aria-labelledby="ai-code-split-title" data-ai-code-split>
    <details @if (! $collapsed) open @endif class="group">
        <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-2 px-3 [&::-webkit-details-marker]:hidden">
            <h2 id="ai-code-split-title" class="eyebrow text-club">{{ $goal ? 'What the AI did, what code did' : 'Who does what' }}</h2>
            <x-icon name="chevron-down" class="size-4 shrink-0 text-ink/70 transition group-open:rotate-180" />
        </summary>
        <div class="grid gap-3 border-t border-line px-3 py-3 sm:grid-cols-2">
            <div>
                <h3 class="text-sm font-semibold text-club">The AI coach drafted</h3>
                @if ($split['ai'])
                    <ul class="mt-1.5 space-y-1 text-[13px] leading-snug text-ink/85">
                        @foreach ($split['ai'] as $line)
                            <li class="flex gap-2"><span class="mt-1.5 size-1.5 shrink-0 rounded-full bg-grass" aria-hidden="true"></span>{{ $line }}</li>
                        @endforeach
                    </ul>
                @else
                    <p class="mt-1.5 text-[13px] text-ink/70">Nothing yet on this goal. Its plan came from code.</p>
                @endif
                @if ($split['sample'])
                    <p class="mt-2 text-xs text-ink/70">In the demo, the coach's answers are saved samples, so no live AI is called and nothing is spent.</p>
                @endif
            </div>
            <div>
                <h3 class="text-sm font-semibold text-ink">Code enforced</h3>
                <ul class="mt-1.5 space-y-1 text-[13px] leading-snug text-ink/85">
                    @foreach ($split['code'] as $line)
                        <li class="flex gap-2"><span class="mt-1.5 size-1.5 shrink-0 rounded-full bg-ink" aria-hidden="true"></span>{{ $line }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    </details>
</section>
