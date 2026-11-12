<x-layouts.app title="Today · GoalLine">
    <x-slot:hero>
        <x-page-header title="Today" :back="route('dashboard')" back-label="Clubhouse" :eyebrow="$gameplan->today->format('l, F j')" subtitle="Trust the process. Show up today." />
    </x-slot:hero>

    @php $kickoffGoal = $kickoffGoals->first(fn ($candidate) => $candidate->isActive() && $candidate->isBeforeKickoff()); @endphp
    @if ($kickoffGoal)
        <x-kickoff-banner :goal="$kickoffGoal" class="mb-4" />
    @endif

    {{-- Each goal's live band: it moves the moment you check something off below. --}}
    @php $bandWeeks = collect($gameplan->entries)->pluck('week')->filter()->unique('id'); @endphp
    @if ($bandWeeks->isNotEmpty())
        <section data-region="today-bands" aria-label="This GoalWeek's bands" class="mb-4 space-y-3 rounded-xl border border-line bg-white px-3 pt-3 pb-1">
            @foreach ($bandWeeks as $bandWeek)
                <x-band-track :week="$bandWeek" :label="$bandWeek->goal->title" />
            @endforeach
        </section>
    @endif

    <x-on-the-line :streak="$streak" />

    <x-todays-gameplan :gameplan="$gameplan" return="today" class="mt-6" />

    <x-habit-grid :gameplan="$gameplan" class="mt-6" />
</x-layouts.app>
