{{-- The matchup scoreboard hero: both players large on the green field. For each: photo (or initials), team or
     display name as the biggest text, the season record right below it, then the live percent of plan with the band
     and the stars if the week ended now. Between them: vs, the week, days left. Then the line. A bye shows the
     league median; a one-member league shows the player against the line. Every number is from MatchupPager. --}}
@props(['page', 'index', 'week'])

@php
    $sides = $page['sides'];
    $final = $page['final'];
    $card = $page['card'];
    $kind = $page['kind'];
@endphp

<section data-pager-hero="{{ $index }}" @if ($index > 0) hidden @endif data-region="matchup-hero-{{ $card['home']['member']->id }}"
    class="gl-field overflow-hidden rounded-2xl text-ivory shadow-sm" aria-label="Scoreboard, GoalWeek {{ $week }}">
    <div class="grid grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)] items-start gap-2 px-3 pt-4 pb-3">
        @foreach ([0, 1] as $slot)
            @if ($slot === 1)
                {{-- The middle: vs, the week, and days left. --}}
                <div class="flex flex-col items-center pt-5 text-center" data-hero-middle>
                    <span class="text-[11px] font-semibold tracking-[0.18em] text-mint">WK {{ $week }}</span>
                    <span class="mt-1 text-[22px] leading-none font-extrabold text-ivory/90">vs</span>
                    <span @class(['mt-1.5 rounded-full px-2 py-0.5 text-[11px] font-semibold whitespace-nowrap', 'bg-ivory/15 text-ivory' => $final, 'bg-ivory text-club' => ! $final])>
                        {{ $final ? 'Final' : ($page['daysLeft'] > 0 ? $page['daysLeft'].'d left' : 'Live') }}
                    </span>
                </div>
            @endif

            @php($side = $sides[$slot] ?? null)
            <div @class(['flex min-w-0 flex-col items-center text-center']) data-hero-side="{{ $slot }}">
                @if ($side)
                    @php($member = $side['member'])
                    @php($profile = $member->user->username ? route('profiles.show', $member->user) : null)
                    <a @if ($profile) href="{{ $profile }}" @endif class="group flex min-w-0 flex-col items-center" aria-label="{{ $member->displayName() }}{{ $profile ? ', open profile' : '' }}">
                        <x-avatar :user="$member->user" tone="light" class="size-16 text-xl ring-3 ring-ivory/80 sm:size-20" />
                        <span class="mt-2 line-clamp-2 max-w-full text-[17px] leading-tight font-extrabold break-words text-ivory group-hover:underline sm:text-xl" data-hero-name title="{{ $member->displayName() }}">{{ $member->displayName() }}</span>
                    </a>
                    <span class="mt-0.5 text-xs font-semibold text-mint tabular-nums" data-hero-record title="Wins, draws and losses this season">{{ $side['record']['wins'] }}&ndash;{{ $side['record']['ties'] }}&ndash;{{ $side['record']['losses'] }}</span>
                    <span class="mt-2 text-[30px] leading-none font-extrabold text-ivory tabular-nums" data-hero-percent>{{ $side['percentLabel'] ?? '–' }}</span>
                    @if ($side['result'])
                        <x-goalweek-result :result="$side['result']" :not-started="$side['notStarted']" compact class="mt-1.5 justify-center" />
                    @endif
                    <span class="mt-1 text-[11px] text-ivory/90"><span class="font-bold text-ivory tabular-nums">+{{ $side['total'] }}</span> {{ Str::plural('star', $side['total']) }}{{ $final ? '' : ' if it ended now' }}</span>
                @elseif ($kind === 'bye')
                    <span class="grid size-16 place-items-center rounded-full bg-ivory/15 ring-3 ring-ivory/40 sm:size-20"><x-icon name="users" class="size-7 text-ivory" /></span>
                    <span class="mt-2 text-[17px] leading-tight font-extrabold text-ivory sm:text-xl">Bye week</span>
                    <span class="mt-0.5 text-xs font-semibold text-mint">No opponent</span>
                    <span class="mt-2 text-[15px] leading-tight font-bold text-ivory/90">Line stars only</span>
                    <span class="mt-1.5 text-[11px] text-ivory/90">Not a win, draw or loss</span>
                @else
                    <span class="grid size-16 place-items-center rounded-full bg-ivory/15 ring-3 ring-ivory/40 sm:size-20"><x-icon name="line" class="size-8 text-ivory" /></span>
                    <span class="mt-2 text-[17px] leading-tight font-extrabold text-ivory sm:text-xl">The line</span>
                    <span class="mt-0.5 text-xs font-semibold text-mint">Solo season</span>
                    <span class="mt-2 text-[30px] leading-none font-extrabold text-ivory/90 tabular-nums">{{ (int) round(App\GoalWeekResult::IN_LINE_AT * 100) }}%</span>
                    <span class="mt-1.5 text-[11px] text-ivory/90">to be In line</span>
                @endif
            </div>
        @endforeach
    </div>

    <p class="px-3 pb-2 text-center text-[11px] text-ivory/90" data-hero-status>{{ $page['status'] }}</p>

    <x-matchup-line :sides="$sides" :final="$final" :index="$index" />
</section>
