{{-- How scoring works, in one place. --}}
<details {{ $attributes->class('group rounded-2xl border border-line bg-white px-4 py-3') }}>
    <summary class="flex cursor-pointer list-none items-center justify-between [&::-webkit-details-marker]:hidden">
        <span class="eyebrow text-club">How scoring works</span>
        <span class="text-ink/70 transition group-open:rotate-45" aria-hidden="true">+</span>
    </summary>
    <div class="mt-3 space-y-3 text-sm text-ink/75">
        <p>GoalLine is about the process. Each GoalWeek has planned points: your tasks, one per habit day, and your milestone. Your band is the share of them you earn:</p>
        <ul class="space-y-1.5">
            <li class="flex items-center justify-between gap-2"><x-goalweek-result :result="App\GoalWeekResult::Short" /> <span>under 90%</span></li>
            <li class="flex items-center justify-between gap-2"><x-goalweek-result :result="App\GoalWeekResult::InLine" /> <span>90&ndash;100%</span></li>
            <li class="flex items-center justify-between gap-2"><x-goalweek-result :result="App\GoalWeekResult::Past" /> <span>101&ndash;149%</span></li>
            <li class="flex items-center justify-between gap-2"><x-goalweek-result :result="App\GoalWeekResult::Smashed" /> <span>150% and up</span></li>
        </ul>
        <p>Bonus tasks, extra habit days, and beating a task&rsquo;s number (7 of a planned 5 miles) push you past 100%. A Smashed week needs proof on a check-in or your opponent&rsquo;s OK to count in full. 90&ndash;94% shows as a close call.</p>
        <p>In GoalLeagues, you also face a weekly matchup opponent. A higher band is a <span class="font-semibold text-ink">Win: 3 match points</span>, and the other side gets 0. In the same band it&rsquo;s a <span class="font-semibold text-ink">Photo finish</span>: the higher credited percent of plan (a whole number) takes a Close win for 2, the other a Close loss for 1, and the exact same percent is a Tie, 1 each. Line points plus match points: 0 to 6 a GoalWeek.</p>
        <p class="text-xs text-ink/70">Credited percent leaves out points held in a dispute, and a Smashed week still waiting on proof counts as Past the line, so it&rsquo;s capped at 149%.</p>
        <p>Check-ins are for today or yesterday. They close the day after a GoalWeek ends, then the result is final.</p>
    </div>
</details>
