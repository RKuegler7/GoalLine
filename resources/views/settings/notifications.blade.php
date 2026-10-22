<x-layouts.app title="Notifications · GoalLine">
    <x-slot:hero>
        <div class="mx-auto max-w-5xl px-4 pt-6 pb-12">
            <a href="{{ route('profile.edit') }}" class="text-sm font-medium text-ivory/75 hover:text-ivory">&larr; Edit profile</a>
            <h1 class="mt-6 font-serif text-4xl font-medium tracking-tight text-ivory sm:text-5xl">Notifications, reminders &amp; calendar</h1>
            <p class="mt-3 max-w-xl text-ivory/85">Daily reminders keep you in line. Everything social always lands in your Inbox; choose what also comes by email.</p>
        </div>
    </x-slot:hero>

    <form method="POST" action="{{ route('settings.notifications.update') }}" class="mx-auto max-w-2xl space-y-6">
        @csrf
        @method('PUT')

        <section class="space-y-5 rounded-2xl border border-line bg-white p-6 sm:p-8">
            <div>
                <h2 class="eyebrow text-club">Daily reminders</h2>
                <p class="mt-1 text-sm text-ink/60">On your clock ({{ str_replace('_', ' ', auth()->user()->timezoneName()) }}).</p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="morning_at" class="mb-1.5 block text-sm font-medium text-ink/80">Morning gameplan</label>
                    <select id="morning_at" name="morning_at" class="block w-full rounded-lg border border-line bg-white px-3.5 py-2.5 text-ink outline-none focus:border-club focus:ring-4 focus:ring-club/10">
                        <option value="" @selected($settings->morningAt === null)>Off</option>
                        @foreach (App\NotificationSettings::MORNING_TIMES as $time)
                            <option value="{{ $time }}" @selected($settings->morningAt === $time)>{{ App\NotificationSettings::label($time) }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1.5 text-sm text-ink/55">What&rsquo;s on today, plus a heads-up when a GoalWeek starts or check-ins are closing.</p>
                </div>
                <div>
                    <label for="evening_at" class="mb-1.5 block text-sm font-medium text-ink/80">Evening nudge</label>
                    <select id="evening_at" name="evening_at" class="block w-full rounded-lg border border-line bg-white px-3.5 py-2.5 text-ink outline-none focus:border-club focus:ring-4 focus:ring-club/10">
                        <option value="" @selected($settings->eveningAt === null)>Off</option>
                        @foreach (App\NotificationSettings::EVENING_TIMES as $time)
                            <option value="{{ $time }}" @selected($settings->eveningAt === $time)>{{ App\NotificationSettings::label($time) }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1.5 text-sm text-ink/55">Only sent when something on today&rsquo;s gameplan is still open.</p>
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-line bg-white p-6 sm:p-8">
            <h2 class="eyebrow text-club">Email me about</h2>
            <ul class="mt-4 divide-y divide-line">
                @foreach (App\NotificationSettings::CATEGORIES as $category => [$label, $description])
                    <li>
                        <label class="flex cursor-pointer items-start justify-between gap-4 py-3">
                            <span>
                                <span class="block font-medium text-ink">{{ $label }}</span>
                                <span class="block text-sm text-ink/60">{{ $description }}</span>
                            </span>
                            <input type="hidden" name="email[{{ $category }}]" value="0">
                            <input type="checkbox" name="email[{{ $category }}]" value="1" class="peer sr-only" @checked($settings->wantsEmail($category))>
                            <span class="relative mt-1 h-6 w-11 shrink-0 rounded-full bg-line transition peer-checked:bg-club peer-focus-visible:ring-4 peer-focus-visible:ring-club/20 after:absolute after:top-0.5 after:left-0.5 after:size-5 after:rounded-full after:bg-white after:shadow after:transition peer-checked:after:translate-x-5" aria-hidden="true"></span>
                        </label>
                    </li>
                @endforeach
            </ul>
            @unless (auth()->user()->hasVerifiedEmail())
                <p class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800">Verify your email address to start getting these.</p>
            @endunless
        </section>

        <div class="flex justify-end">
            <x-form.button class="sm:w-auto sm:px-8">Save settings</x-form.button>
        </div>
    </form>

    {{-- Calendar subscription. --}}
    @php
        $feedUrl = auth()->user()->calendarUrl();
        $webcalUrl = preg_replace('/^https?:/', 'webcal:', $feedUrl);
    @endphp
    <section id="calendar" class="mx-auto mt-6 max-w-2xl scroll-mt-6 rounded-2xl border border-line bg-white p-6 sm:p-8">
        <h2 class="eyebrow flex items-center gap-1.5 text-club"><x-icon name="calendar" class="size-4" /> Add GoalLine to your calendar</h2>
        <p class="mt-2 text-sm text-ink/65">Your planned tasks on their days, recurring habits, GoalWeek milestones, and target dates, kept in sync automatically.</p>

        <div class="mt-4 flex flex-wrap gap-2">
            <a href="https://calendar.google.com/calendar/render?cid={{ urlencode($webcalUrl) }}" target="_blank" rel="noopener" class="rounded-full bg-club px-4 py-2 text-sm font-semibold text-ivory hover:bg-club-dark">Google Calendar</a>
            <a href="{{ $webcalUrl }}" class="rounded-full border border-club/30 px-4 py-2 text-sm font-semibold text-club hover:border-club">Apple Calendar</a>
            <a href="https://outlook.live.com/calendar/0/addfromweb?url={{ urlencode($feedUrl) }}&name=GoalLine" target="_blank" rel="noopener" class="rounded-full border border-club/30 px-4 py-2 text-sm font-semibold text-club hover:border-club">Outlook</a>
        </div>

        <label for="calendar-url" class="mt-5 block text-sm font-medium text-ink/80">Or subscribe with this link in any calendar app</label>
        <div class="mt-1.5 flex gap-2">
            <input id="calendar-url" type="text" readonly value="{{ $feedUrl }}" onclick="this.select()"
                class="min-w-0 flex-1 rounded-lg border border-line bg-ivory/60 px-3 py-2 font-mono text-xs text-ink/75 outline-none focus:border-club">
            <button type="button" data-copy="calendar-url" class="shrink-0 rounded-lg border border-line px-3 py-2 text-sm font-semibold text-club hover:border-club/40">Copy</button>
        </div>
        <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-xs text-ink/55">
            <span>Keep this link private: anyone with it can see your plan.</span>
            <form method="POST" action="{{ route('calendar.reset') }}" onsubmit="return confirm('Make a new link? Calendars using the old one will stop updating.')">
                @csrf
                <button type="submit" class="font-semibold text-ink/60 hover:text-red-700">Make a new link</button>
            </form>
        </div>
    </section>
</x-layouts.app>
