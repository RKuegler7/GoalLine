@php
    $title = $payload['name'].'’s season on GoalLine';
    $description = App\Services\ResultCards\SeasonCardImage::describe($payload);
    $cardImage = App\ShareLink::route('finale.public.image', [$token, 'feed', $canMakePng ? 'png' : 'svg']);
@endphp

<x-layouts.field :title="$title">
    @push('head')
        <meta name="robots" content="noindex">
        @if ($canMakePng)
            @include('partials.share-meta', ['title' => $title, 'description' => $description, 'url' => App\ShareLink::route('finale.public', $token), 'image' => $cardImage, 'imageWidth' => 1080, 'imageHeight' => 1350, 'imageAlt' => $description])
        @else
            @include('partials.share-meta', ['title' => $title, 'description' => $description, 'url' => App\ShareLink::route('finale.public', $token)])
        @endif
    @endpush

    {{-- A shared season: just the card someone chose to share, and a way in. Read only. --}}
    <div class="mx-auto max-w-md px-4 py-8" data-public-season-card>
        <div class="overflow-hidden rounded-2xl shadow-lg [&>svg]:block [&>svg]:h-auto [&>svg]:w-full">{!! $svg !!}</div>
        <p class="mt-4 text-center text-sm text-ink/75">Stars and standings on GoalLine are scored by plain code against each player's own plan. Goal Day results are self-reported.</p>
        <div class="mt-4 flex flex-wrap justify-center gap-2">
            <a href="{{ route('home') }}" class="inline-flex min-h-11 items-center rounded-full bg-club px-5 text-sm font-semibold text-ivory hover:bg-club-dark">Join GoalLine</a>
            <a href="{{ route('demo') }}" class="inline-flex min-h-11 items-center rounded-full border border-club/30 px-5 text-sm font-semibold text-club hover:border-club">Try the demo</a>
        </div>
    </div>
</x-layouts.field>
