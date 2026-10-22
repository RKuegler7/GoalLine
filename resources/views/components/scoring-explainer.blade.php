{{-- How scoring works, in one place. --}}
<details {{ $attributes->class('group rounded-2xl border border-line bg-white px-5 py-4') }}>
    <summary class="flex cursor-pointer list-none items-center justify-between [&::-webkit-details-marker]:hidden">
        <span class="eyebrow text-club">How scoring works</span>
        <span class="text-ink/40 transition group-open:rotate-45" aria-hidden="true">+</span>
    </summary>
    <div class="mt-3 space-y-3 text-sm text-ink/75">
        <p>Each GoalWeek, your result is based on your planned tasks and your milestone:</p>
        <ul class="space-y-1.5">
            <li class="flex items-center justify-between gap-2"><x-goalweek-result :result="App\GoalWeekResult::Short" /> <span>under 70% of tasks</span></li>
            <li class="flex items-center justify-between gap-2"><x-goalweek-result :result="App\GoalWeekResult::InLine" /> <span>70% or more</span></li>
            <li class="flex items-center justify-between gap-2"><x-goalweek-result :result="App\GoalWeekResult::Past" /> <span>all tasks + milestone</span></li>
            <li class="flex items-center justify-between gap-2"><x-goalweek-result :result="App\GoalWeekResult::Smashed" /> <span>past + bonus work</span></li>
        </ul>
        <p>Bonus tasks are ones you add during the GoalWeek. 70&ndash;79% shows as a close call.</p>
        <p>In GoalLeagues, you also play a GoalGroup opponent: <span class="font-semibold text-ink">win 3, tie 1, loss 0</span>. Up to 6 points a GoalWeek.</p>
        <p>Check-ins close the day after a GoalWeek ends, then the result is final.</p>
    </div>
</details>
