<x-layouts.app title="Share your week · GoalLine">
    <x-slot:hero>
        <x-page-header title="Share your week" :back="route('recap.week')" back-label="Your week"
            subtitle="A card made from your scored results. Post it, send it, or save it." />
    </x-slot:hero>

    @php
        $p = $card->payload;
        $png = fn (string $size) => route('cards.image', [$card, $size, 'png']);
        $svg = fn (string $size) => route('cards.image', [$card, $size, 'svg']);
        $fileName = 'goalline-'.Str::slug($p['weekLabel']).'.png';
    @endphp

    <div class="mx-auto grid max-w-4xl gap-5 md:grid-cols-[minmax(0,1fr)_minmax(0,22rem)]" data-result-card
        data-card-name="{{ $fileName }}" data-card-title="{{ App\Services\ResultCards\ResultCardImage::describe($p) }}"
        data-card-png="{{ $canMakePng ? '1' : '' }}">
        {{-- The card: feed (4:5) or story (9:16). Shown inline, so it uses the app's own fonts. --}}
        <section data-segmented aria-label="Card preview">
            <x-segmented label="Card size" class="mx-auto mb-3 max-w-xs" :options="[
                ['label' => 'Feed', 'segment' => 'feed', 'active' => true],
                ['label' => 'Story', 'segment' => 'story'],
            ]" />
            <div data-segment-panel="feed" data-card-size="feed" data-card-png-url="{{ $png('feed') }}" data-card-svg-url="{{ $svg('feed') }}"
                class="mx-auto max-w-sm overflow-hidden rounded-2xl shadow-lg [&>svg]:block [&>svg]:h-auto [&>svg]:w-full">{!! $feedSvg !!}</div>
            <div data-segment-panel="story" data-card-size="story" data-card-png-url="{{ $png('story') }}" data-card-svg-url="{{ $svg('story') }}" hidden
                class="mx-auto max-w-[17rem] overflow-hidden rounded-2xl shadow-lg [&>svg]:block [&>svg]:h-auto [&>svg]:w-full">{!! $storySvg !!}</div>
            <p class="mt-3 text-center text-xs text-ink/70">
                {{ $p['final'] ? 'Final result.' : 'Results so far: the week is still going.' }}
                Numbers come from GoalLine’s scoring, never from the AI.
            </p>
        </section>

        <div class="space-y-4">
            {{-- Share and save. --}}
            <section class="rounded-2xl border border-line bg-white p-4">
                <h2 class="eyebrow text-club">Send it</h2>
                <div class="mt-3 grid gap-2">
                    <button type="button" data-card-share hidden
                        class="inline-flex min-h-12 items-center justify-center gap-2 rounded-full bg-club px-5 text-[15px] font-semibold text-ivory hover:bg-club-dark">
                        <x-icon name="share" class="size-5" /> Share
                    </button>
                    <a href="{{ $canMakePng ? $png('feed') : $svg('feed') }}" download="{{ $canMakePng ? $fileName : Str::replaceLast('.png', '.svg', $fileName) }}" data-card-download
                        class="inline-flex min-h-12 items-center justify-center gap-2 rounded-full border border-club/30 px-5 text-[15px] font-semibold text-club hover:border-club">
                        Download image
                    </a>
                </div>
                <p data-card-status role="status" aria-live="polite" class="mt-2 text-xs text-ink/70"></p>
            </section>

            {{-- Post to Highlights. --}}
            <section class="rounded-2xl border border-line bg-white p-4">
                <h2 class="eyebrow text-club">Post to Highlights</h2>
                @if ($posted)
                    <p class="mt-2 text-sm text-ink/80">You posted this week’s card.</p>
                    <a href="{{ route('highlights.index') }}#highlight-{{ $posted->id }}" class="mt-2 inline-flex min-h-10 items-center font-semibold text-club hover:underline">See your post &rarr;</a>
                @else
                    <form method="POST" action="{{ route('cards.post', $card) }}" class="mt-2 space-y-3">
                        @csrf
                        <div>
                            <label for="card-caption" class="text-sm text-ink/70">Caption (optional)</label>
                            <textarea id="card-caption" name="caption" rows="2" maxlength="{{ App\Models\Highlight::MAX_CAPTION }}"
                                class="mt-1 block w-full rounded-lg border border-line bg-ivory px-3 py-2 text-base outline-none focus:border-club"
                                placeholder="What made this week work?">{{ old('caption') }}</textarea>
                            @error('caption')
                                <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                            @enderror
                        </div>
                        <fieldset>
                            <legend class="text-sm text-ink/70">Who sees it</legend>
                            <div class="mt-1 grid gap-1.5">
                                @foreach ($inLeague ? [App\PostVisibility::LeagueFriends, App\PostVisibility::League] : [App\PostVisibility::LeagueFriends] as $choice)
                                    <label class="flex cursor-pointer items-start gap-2.5 rounded-lg border border-line px-3 py-2 has-checked:border-club has-checked:bg-mint">
                                        <input type="radio" name="visibility" value="{{ $choice->value }}" class="mt-1 accent-club" @checked(old('visibility', App\PostVisibility::LeagueFriends->value) === $choice->value)>
                                        <span>
                                            <span class="block text-sm font-semibold text-ink">{{ $choice->label($inLeague) }}</span>
                                            <span class="block text-xs text-ink/70">{{ $choice->description($inLeague) }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                        <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center rounded-full bg-grass px-5 text-sm font-bold text-ink hover:brightness-105">Post to Highlights</button>
                    </form>
                @endif
            </section>

            {{-- A public link: off until you turn it on. --}}
            <section class="rounded-2xl border border-line bg-white p-4" data-region="card-link">
                <h2 class="eyebrow text-club">Link anyone can open</h2>
                @if ($card->isPublic())
                    <x-share-link class="mt-2" :url="$card->publicUrl()" id="card-public-url" label="Your card’s link"
                        :share-message="'My '.$p['weekLabel'].' on GoalLine: '.$card->publicUrl()" share-title="My GoalLine week" />
                    <form method="POST" action="{{ route('cards.revoke', $card) }}" class="mt-2">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-sm font-semibold text-ink/70 hover:text-ink hover:underline">Turn the link off</button>
                    </form>
                @elseif (! auth()->user()->public_cards)
                    <p class="mt-2 text-sm text-ink/80">Public card links are off for your account. <a href="{{ route('profile.edit') }}#public-cards" class="font-semibold text-club hover:underline">Change it in settings</a>.</p>
                @else
                    <p class="mt-2 text-sm text-ink/80">Only this card: your first name, goal, and this week’s numbers. Never your email, notes or proof photos. Turn it off anytime.</p>
                    <form method="POST" action="{{ route('cards.publish', $card) }}" class="mt-3">
                        @csrf
                        <button type="submit" class="inline-flex min-h-11 w-full items-center justify-center rounded-full border border-club/30 px-5 text-sm font-semibold text-club hover:border-club">Make a link</button>
                    </form>
                @endif
            </section>
        </div>
    </div>
</x-layouts.app>
