<x-layouts.app title="Share a highlight · GoalLine">
    <x-slot:hero>
        <div class="mx-auto max-w-5xl px-4 pt-6 pb-12">
            <p class="eyebrow text-mint">Highlights</p>
            <h1 class="mt-3 font-serif text-4xl font-bold tracking-tight text-ivory sm:text-5xl">Share to Highlights</h1>
            <p class="mt-3 max-w-xl text-ivory/85">Pick a moment worth showing. Your followers and GoalLeague mates will see it in their feed.</p>
        </div>
    </x-slot:hero>

    <div class="mx-auto max-w-2xl">
        @if ($achievements->isEmpty())
            <x-empty-state title="Nothing to share yet"
                message="A GoalWeek that finishes Past the Line, a milestone you hit, or a goal you complete shows up here, ready to share."
                action="Back to Highlights" :href="route('highlights.index')" />
        @else
            <ul data-highlight-picker class="divide-y divide-line overflow-hidden rounded-2xl border border-line bg-white">
                @foreach ($achievements as $achievement)
                    <li>
                        <a href="{{ $achievement['url'] }}" class="flex min-h-14 items-center gap-4 px-4 py-3 hover:bg-mint/50 sm:px-6">
                            <span class="min-w-0 flex-1">
                                <span class="eyebrow block text-[11px] text-club">{{ $achievement['label'] }}</span>
                                <span class="mt-0.5 block font-semibold text-ink">{{ $achievement['title'] }}</span>
                            </span>
                            <span class="shrink-0 text-sm font-semibold text-club">Share</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-layouts.app>
