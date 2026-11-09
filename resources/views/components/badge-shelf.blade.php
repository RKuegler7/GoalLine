{{-- GoalLeague awards and badges on a locker. Your own locker also shows the badges still to earn. --}}
@props(['user', 'isOwn' => false])

@php
    $earned = $user->badges()->with('league')->get();
    $awards = $earned->filter(fn ($badge) => $badge->league_id !== null);
    $personal = $earned->whereNull('league_id')->keyBy(fn ($badge) => $badge->badge->value);
    $shown = $isOwn ? collect(App\Badge::personal()) : collect(App\Badge::personal())->filter(fn ($badge) => $personal->has($badge->value));
@endphp

{{-- Folded to its count until opened, so a locker stays short. --}}
<details id="badges" {{ $attributes->class('group scroll-mt-20 rounded-xl border border-line bg-white') }}>
    <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-2 px-3 [&::-webkit-details-marker]:hidden">
        <h2 class="eyebrow text-club">Badges &amp; awards</h2>
        <span class="ml-auto text-[13px] text-ink/70">{{ $personal->count() }} of {{ count(App\Badge::personal()) }} badges{{ $awards->isNotEmpty() ? ' · '.$awards->count().' '.Str::plural('award', $awards->count()) : '' }}</span>
        <x-icon name="chevron-down" class="size-4 shrink-0 text-ink/70 transition group-open:rotate-180" />
    </summary>
    <div class="border-t border-line px-3 pb-3">

    @if ($awards->isNotEmpty())
        <ul class="mt-3 grid gap-3 sm:grid-cols-2">
            @foreach ($awards as $award)
                <li>
                    <a href="{{ route('leagues.recap', $award->league) }}" class="flex items-center gap-4 rounded-2xl border border-gold/60 bg-gold/10 px-4 py-3 transition hover:bg-gold/20">
                        <x-badge :badge="$award->badge" class="size-14" />
                        <span class="min-w-0">
                            <span class="block font-serif text-lg font-bold text-ink tracking-tight">{{ $award->badge->label() }}</span>
                            <span class="block truncate text-sm text-ink/65">{{ $award->league->name }} &middot; {{ $award->earned_at->format('M Y') }}</span>
                            @if ($award->detail)
                                <span class="block truncate text-xs text-ink/70">{{ $award->detail }}</span>
                            @endif
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif

    @if ($shown->isEmpty())
        <p class="mt-3 rounded-2xl border border-line bg-white px-4 py-3 text-ink/70">No badges yet.</p>
    @else
        <ul class="mt-3 grid grid-cols-3 gap-3 rounded-2xl border border-line bg-white p-4 sm:grid-cols-6">
            @foreach ($shown as $badge)
                @php
                    $mine = $personal->get($badge->value);
                @endphp
                <li class="flex flex-col items-center text-center" title="{{ $badge->description() }}">
                    <x-badge :badge="$badge" :earned="(bool) $mine" class="size-14" />
                    <span @class(['mt-1.5 text-xs font-semibold leading-tight', 'text-ink' => $mine, 'text-ink/70' => ! $mine])>{{ $badge->label() }}</span>
                    <span class="mt-0.5 text-[11px] leading-tight text-ink/70">{{ $mine ? $mine->earned_at->format('M j, Y') : $badge->description() }}</span>
                </li>
            @endforeach
        </ul>
    @endif
    </div>
</details>
