<x-layouts.welcome :step="1" title="Welcome to GoalLine" intro="Let's set up your locker. You can change any of this later.">
    <form method="POST" action="{{ route('welcome.you') }}" enctype="multipart/form-data" class="space-y-6 rounded-2xl border border-line bg-white p-6 sm:p-8">
        @csrf
        @method('PUT')

        <div class="flex items-center gap-5">
            <x-avatar :user="$user" class="size-20 text-2xl" />
            <div class="min-w-0 flex-1">
                <label for="avatar" class="mb-1.5 block text-sm font-medium text-ink/80">Profile photo (optional)</label>
                <input id="avatar" name="avatar" type="file" accept="image/jpeg,image/png,image/webp"
                    class="block w-full text-sm text-ink/70 file:mr-4 file:rounded-lg file:border-0 file:bg-mint file:px-4 file:py-2 file:text-sm file:font-semibold file:text-club hover:file:bg-grass/20">
                @error('avatar')
                    <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>
        </div>

        <x-form.input name="name" label="Your name" :value="$user->name" autocomplete="name" required />
        <x-form.input name="username" label="Pick a username" :value="$user->username" maxlength="30" required autofocus
            hint="Friends and GoalLeague mates find you by this: lowercase letters, numbers, dots, and underscores." />

        <div>
            <label for="timezone" class="mb-1.5 block text-sm font-medium text-ink/80">Your time zone</label>
            <select id="timezone" name="timezone" data-detect-timezone-select class="block w-full rounded-lg border border-line bg-white px-3.5 py-2.5 text-ink outline-none focus:border-club focus:ring-4 focus:ring-club/10">
                @foreach ($timezones as $region => $zones)
                    <optgroup label="{{ $region }}">
                        @foreach ($zones as $zone)
                            <option value="{{ $zone }}" @selected(old('timezone', $user->timezoneName()) === $zone && ($region === 'Common in the US' || ! in_array($zone, $timezones['Common in the US'], true)))>{{ str_replace('_', ' ', $zone) }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
            <button type="button" hidden data-use-browser-timezone="timezone" class="mt-2 text-sm font-semibold text-club hover:underline"></button>
            <p class="mt-1.5 text-sm text-ink/55">Your GoalWeeks run Monday to Sunday on this clock.</p>
        </div>

        <fieldset>
            <legend class="mb-2 block text-sm font-medium text-ink/80">Who can see your locker?</legend>
            <div class="space-y-2.5">
                @foreach (App\ProfileVisibility::cases() as $visibility)
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-line px-4 py-3 transition hover:border-club/40 has-checked:border-club has-checked:bg-mint">
                        <input type="radio" name="profile_visibility" value="{{ $visibility->value }}" class="mt-1 accent-club"
                            @checked(old('profile_visibility', $user->profile_visibility?->value ?? 'leagues') === $visibility->value)>
                        <span>
                            <span class="block font-medium text-ink">{{ $visibility->label() }}</span>
                            <span class="block text-sm text-ink/60">{{ $visibility->description() }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
        </fieldset>

        <div class="flex justify-end border-t border-line pt-6">
            <x-form.button class="sm:w-auto sm:px-8">Next: your goal</x-form.button>
        </div>
    </form>
</x-layouts.welcome>
