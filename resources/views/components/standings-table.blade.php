{{-- The league table: rank, player (avatar, team or display name, record underneath), line points, points and the last
     five weeks' bands. The player column stays put while the table scrolls sideways inside its own box; the page never
     scrolls sideways. Each row opens the player's season. Numbers come from LeagueTable and LeagueStats. --}}
@props(['rows', 'league', 'me' => null])

@php
    $chip = fn ($band) => match ($band) {
        App\GoalWeekResult::Smashed => ['bg-sky-700 text-white', 'S', 'Smashed'],
        App\GoalWeekResult::Past => ['bg-gold text-ink', 'P', 'Past the line'],
        App\GoalWeekResult::InLine => ['bg-grass text-ink', 'L', 'In line'],
        default => ['bg-red-100 text-red-800', '–', 'Short'],
    };
@endphp

<div {{ $attributes->class('relative overflow-x-auto rounded-xl border border-line bg-white') }} data-standings>
    <table class="w-full min-w-[22rem] text-left text-sm">
        <thead class="gl-field text-[11px] tracking-wide text-ivory uppercase">
            <tr>
                <th scope="col" class="sticky left-0 z-10 bg-club py-2 pr-1 pl-3 font-semibold">Player</th>
                <th scope="col" class="px-1.5 py-2 text-center font-semibold" title="Line points: 0 to 3 a week for your band">Line</th>
                <th scope="col" class="px-1.5 py-2 text-center font-semibold">Pts</th>
                <th scope="col" class="py-2 pr-3 pl-1.5 text-right font-semibold">Last {{ App\Services\LeagueStats::FORM_WEEKS }}</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-line">
            @foreach ($rows as $row)
                @php
                    $member = $row['member'];
                    $mine = $me && $member->is($me);
                    $stats = $row['stats'];
                @endphp
                <tr @class(['group', 'bg-mint' => $mine]) data-standings-row="{{ $member->id }}">
                    <th scope="row" @class(['sticky left-0 z-10 py-1.5 pr-1 pl-3 font-normal', 'bg-mint' => $mine, 'bg-white group-hover:bg-ivory' => ! $mine])>
                        <a href="{{ route('leagues.standings.player', [$league, $member]) }}" class="flex min-h-11 items-center gap-2" aria-label="{{ $row['rank'] }}. {{ $member->displayName() }}, see their season">
                            <span class="w-4 shrink-0 text-right text-xs font-semibold text-ink/70 tabular-nums">{{ $row['rank'] }}</span>
                            <x-avatar :user="$member->user" class="size-8 text-[11px]" />
                            <span class="min-w-0">
                                <span class="block max-w-[9.5rem] truncate font-semibold text-ink group-hover:text-club sm:max-w-none">{{ $member->displayName() }}</span>
                                <span class="block text-xs text-ink/70 tabular-nums">{{ $row['wins'] }}&ndash;{{ $row['ties'] }}&ndash;{{ $row['losses'] }}</span>
                            </span>
                        </a>
                    </th>
                    <td class="px-1.5 py-1.5 text-center text-ink tabular-nums">{{ $row['resultPoints'] }}</td>
                    <td class="px-1.5 py-1.5 text-center text-lg font-bold text-club tabular-nums">{{ $row['total'] }}</td>
                    <td class="py-1.5 pr-3 pl-1.5">
                        <span class="flex justify-end gap-0.5" data-form>
                            @forelse ($stats['form'] as $band)
                                @php([$classes, $letter, $label] = $chip($band))
                                <span class="grid size-5 place-items-center rounded text-[10px] font-bold {{ $classes }}" title="{{ $label }}"><span aria-hidden="true">{{ $letter }}</span><span class="sr-only">{{ $label }}</span></span>
                            @empty
                                <span class="text-xs text-ink/60">No weeks yet</span>
                            @endforelse
                        </span>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
