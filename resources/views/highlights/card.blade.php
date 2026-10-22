@php
    $kindColor = match ($highlight->kind) {
        App\HighlightKind::GoalCompleted => '#d4ac58',
        App\HighlightKind::GreatWeek => '#43b96a',
        App\HighlightKind::MilestoneHit => '#e5f3e8',
    };
    $titleTop = 950;
    $lineHeight = 88;
    $afterTitle = $titleTop + (count($titleLines) - 1) * $lineHeight;
@endphp

<x-layouts.app title="Share card · GoalLine">
    <x-slot:hero>
        <div class="mx-auto max-w-5xl px-4 pt-6 pb-12">
            <a href="{{ route('highlights.index') }}#highlight-{{ $highlight->id }}" class="text-sm font-medium text-ivory/75 hover:text-ivory">&larr; Highlights</a>
            <h1 class="mt-6 font-serif text-4xl font-medium tracking-tight text-ivory sm:text-5xl">Your share card</h1>
            <p class="mt-3 max-w-xl text-ivory/85">Post it on Instagram, your story, or anywhere you celebrate.</p>
        </div>
    </x-slot:hero>

    <div class="mx-auto flex max-w-md flex-col items-center gap-5">
        {{-- 1080 × 1350 (4:5), Instagram's portrait size. --}}
        <svg data-share-card viewBox="0 0 1080 1350" xmlns="http://www.w3.org/2000/svg" class="w-full rounded-2xl shadow-xl shadow-ink/15"
            role="img" aria-label="Share card: {{ $highlight->kind->label() }}, {{ $highlight->headline }}">
            <defs>
                <linearGradient id="card-fade" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0.45" stop-color="#176b45" stop-opacity="0" />
                    <stop offset="1" stop-color="#176b45" stop-opacity="0.95" />
                </linearGradient>
            </defs>

            <rect width="1080" height="1350" fill="#176b45" />

            @if ($photo)
                <image href="{{ $photo }}" x="0" y="0" width="1080" height="770" preserveAspectRatio="xMidYMid slice" />
                <rect x="0" y="0" width="1080" height="770" fill="url(#card-fade)" />
            @else
                <x-field-backdrop x="0" y="0" width="1080" height="770" />
            @endif

            {{-- The goal line. --}}
            <rect x="0" y="770" width="1080" height="12" fill="#ffffff" />
            <rect x="0" y="782" width="1080" height="3" fill="#43b96a" />

            <text x="80" y="865" fill="{{ $kindColor }}" font-family="Instrument Sans, sans-serif" font-weight="600" font-size="30" letter-spacing="7">{{ Str::upper($highlight->kind->label()) }}</text>

            @foreach ($titleLines as $index => $line)
                <text x="80" y="{{ $titleTop + $index * $lineHeight }}" fill="#fafaf5" font-family="Fraunces, Georgia, serif" font-weight="500" font-size="78">{{ $line }}</text>
            @endforeach

            <text x="80" y="{{ $afterTitle + 70 }}" fill="#e5f3e8" fill-opacity="0.85" font-family="Instrument Sans, sans-serif" font-size="34">{{ $highlight->user->name }} &#183; {{ App\Clock::local($highlight->created_at)->format('F j, Y') }}</text>
            @if ($highlight->detail)
                <text x="80" y="{{ $afterTitle + 122 }}" fill="#e5f3e8" fill-opacity="0.65" font-family="Instrument Sans, sans-serif" font-size="28">{{ Str::limit($highlight->detail, 58) }}</text>
            @endif

            {{-- The GoalLine lockup, bottom left. --}}
            <g transform="translate(76 1200) scale(0.95)" style="color: #fafaf5">
                <x-brand.letters />
                <g stroke-width="6" stroke-linecap="round">
                    <path d="M420 73 H468" stroke="#43b96a" />
                    <path d="M468 73 H514" stroke="#e5f3e8" stroke-opacity="0.6" />
                    <path d="M514 49 V97" stroke="#e5f3e8" />
                </g>
                <circle cx="468" cy="73" r="10" fill="#43b96a" />
            </g>
            <text x="1000" y="1268" text-anchor="end" fill="#e5f3e8" fill-opacity="0.7" font-family="Instrument Sans, sans-serif" font-weight="600" font-size="22" letter-spacing="5">GET IN LINE.</text>
        </svg>

        <button type="button" data-download-card data-filename="goalline-{{ Str::slug($highlight->headline) }}.png"
            class="w-full rounded-xl bg-club px-5 py-3 font-semibold text-ivory hover:bg-club-dark">
            Download image
        </button>
        <p class="text-center text-sm text-ink/55">On a phone, you can also press and hold the card to save it.</p>
    </div>
</x-layouts.app>
