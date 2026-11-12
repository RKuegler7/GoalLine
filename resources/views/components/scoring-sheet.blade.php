{{-- The scoring sheet: how a GoalWeek is scored, in order: one sentence, the four bands, match points and the photo
     finish, the week total, the table's tiebreakers, and an example from your own league results when there is one.
     Every number comes from App\Services\ScoringRules, which reads the scoring code, so this can't drift from the
     real rules. Closes with the X, a tap outside, a swipe down or Back (sheets.js). --}}
@php
    $rules = app(App\Services\ScoringRules::class);
    $bands = $rules->bands();
    $outcomes = $rules->outcomes();
    $range = $rules->weekRange();
    $latest = auth()->check()
        ? App\Models\LeagueResult::query()->where('user_id', auth()->id())->latest('updated_at')->first()
        : null;
    $chip = fn ($result) => match ($result) {
        App\GoalWeekResult::Short => 'bg-red-50 text-red-800',
        App\GoalWeekResult::InLine => 'bg-grass text-ink',
        App\GoalWeekResult::Past => 'bg-gradient-to-r from-gold via-[#f0dca4] to-gold text-ink',
        App\GoalWeekResult::Smashed => 'bg-sky-700 text-white',
    };
@endphp

<dialog id="scoring-sheet" aria-labelledby="scoring-sheet-title" data-scoring-sheet
    class="gl-sheet m-0 mt-auto w-full max-w-none rounded-t-2xl bg-ivory p-0 text-ink shadow-2xl backdrop:bg-ink/40 sm:m-auto sm:max-w-lg sm:rounded-2xl">
    <div class="flex items-center justify-between gap-3 border-b border-line bg-white px-4 py-2">
        <span class="mx-auto h-1.5 w-10 rounded-full bg-ink/15 sm:hidden" aria-hidden="true"></span>
    </div>
    <div class="flex items-start justify-between gap-3 bg-white px-4 pb-2">
        <h2 id="scoring-sheet-title" class="text-[18px] font-bold text-club">How is GoalLine scored?</h2>
        <form method="dialog">
            <button type="submit" class="-mt-1 grid size-11 place-items-center rounded-full text-ink/70 hover:bg-ivory" aria-label="Close"><x-icon name="x" class="size-5" /></button>
        </form>
    </div>
    <div data-sheet-scroll class="max-h-[75dvh] space-y-4 overflow-y-auto px-4 pt-3 pb-[max(1.25rem,env(safe-area-inset-bottom))] text-[14px]">
        <p class="text-[15px] text-ink">Every week, your percent of your own plan sets your band, and bands decide your matchup.</p>

        <section aria-labelledby="scoring-bands">
            <h3 id="scoring-bands" class="eyebrow text-[11px] text-club">Bands</h3>
            <table class="mt-1.5 w-full text-left text-[13px]" data-scoring-bands>
                <thead class="text-[11px] text-ink/70"><tr><th scope="col" class="py-1 font-semibold">Band</th><th scope="col" class="py-1 font-semibold">Percent of plan</th><th scope="col" class="py-1 text-right font-semibold">Line points</th></tr></thead>
                <tbody class="divide-y divide-line">
                    @foreach ($bands as $band)
                        <tr data-band="{{ $band['result']->value }}">
                            <td class="py-1.5"><span class="rounded-full px-2 py-0.5 text-[12px] font-semibold {{ $chip($band['result']) }}">{{ $band['label'] }}</span></td>
                            <td class="py-1.5 tabular-nums">{{ $band['range'] }}</td>
                            <td class="py-1.5 text-right font-semibold tabular-nums">{{ $band['points'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <p class="mt-1 text-xs text-ink/70">{{ $bands[3]['label'] }} needs proof on a check-in or your opponent's OK to count in full; until then it counts as {{ $bands[2]['label'] }}.</p>
        </section>

        <section aria-labelledby="scoring-match">
            <h3 id="scoring-match" class="eyebrow text-[11px] text-club">Match points</h3>
            <table class="mt-1.5 w-full text-left text-[13px]" data-scoring-outcomes>
                <tbody class="divide-y divide-line">
                    @foreach ($outcomes as $outcome)
                        <tr data-outcome="{{ $outcome['outcome']->value }}">
                            <th scope="row" class="py-1.5 font-semibold">{{ $outcome['label'] }}</th>
                            <td class="py-1.5 text-ink/75">{{ $outcome['when'] }}</td>
                            <td class="py-1.5 text-right font-semibold tabular-nums">{{ $outcome['points'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <p class="mt-1 text-xs text-ink/70"><span class="font-semibold text-ink">Photo finish:</span> both players in the same band. The higher credited percent of plan (a whole number) wins it.</p>
        </section>

        <section aria-labelledby="scoring-week">
            <h3 id="scoring-week" class="eyebrow text-[11px] text-club">Your week</h3>
            <p class="mt-1" data-week-range>Line points plus match points: {{ $range['min'] }} to {{ $range['max'] }} points a week.</p>
            <p class="mt-1 text-ink/80"><span class="font-semibold">The table:</span> {{ implode(', ', array_map('lcfirst', $rules->tableOrder())) }}.</p>
            @if ($latest && $latest->outcome)
                <p class="mt-2 rounded-lg bg-white px-3 py-2 text-[13px] ring-1 ring-line" data-scoring-example>
                    <span class="font-semibold">Your latest league week, week {{ $latest->week }}:</span>
                    {{ $latest->result?->label() ?? 'No GoalWeek' }} ({{ $latest->result_points }} line {{ Str::plural('point', $latest->result_points) }})
                    + {{ $latest->outcome->label() }} ({{ $latest->matchup_points }} match {{ Str::plural('point', $latest->matchup_points) }})
                    = <span class="font-semibold">{{ $latest->result_points + $latest->matchup_points }} {{ Str::plural('point', $latest->result_points + $latest->matchup_points) }}</span>.
                </p>
            @endif
        </section>

        <p class="flex flex-wrap gap-x-4 gap-y-1 border-t border-line pt-3 text-[13px] font-semibold">
            <a href="{{ route('about') }}#fair-play" class="text-club hover:underline">Why proof matters</a>
            <a href="{{ route('about') }}#faq" data-open-help="scoring" class="text-club hover:underline">Open the full Help center</a>
        </p>
    </div>
</dialog>
