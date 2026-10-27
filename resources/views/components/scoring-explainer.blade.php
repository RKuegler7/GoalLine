{{-- How scoring works, in one place. --}}
<details {{ $attributes->class('group rounded-2xl border border-line bg-white px-5 py-4') }}>
    <summary class="flex cursor-pointer list-none items-center justify-between [&::-webkit-details-marker]:hidden">
        <span class="eyebrow text-club">How scoring works</span>
        <span class="text-ink/40 transition group-open:rotate-45" aria-hidden="true">+</span>
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
        <p>In GoalLeagues, you also play a GoalGroup opponent: the better band <span class="font-semibold text-ink">wins 2, the same band draws 1</span>. Line points plus match points: up to 5 a GoalWeek.</p>
        <p>Check-ins are for today or yesterday. They close the day after a GoalWeek ends, then the result is final.</p>
    </div>
</details>
