<x-layouts.app title="Edit profile · GoalLine">
    <x-slot:hero>
        <div class="mx-auto max-w-5xl px-4 pt-6 pb-12">
            <a href="{{ route('profile.show') }}" class="text-sm font-medium text-ivory/75 hover:text-ivory">&larr; Profile</a>
            <h1 class="mt-6 font-serif text-4xl font-medium tracking-tight text-ivory sm:text-5xl">Edit profile</h1>
        </div>
    </x-slot:hero>

    <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="mx-auto max-w-2xl space-y-6">
        @csrf
        @method('PUT')

        {{-- Photos. --}}
        <section class="space-y-6 rounded-2xl border border-line bg-white p-6 sm:p-8">
            <h2 class="eyebrow text-club">Photos</h2>

            <div class="flex items-center gap-5">
                <x-avatar :user="$user" class="size-20 text-2xl" />
                <div class="min-w-0 flex-1">
                    <label for="avatar" class="mb-1.5 block text-sm font-medium text-ink/80">Profile photo</label>
                    <input id="avatar" name="avatar" type="file" accept="image/jpeg,image/png,image/webp" data-crop="avatar"
                        class="block w-full text-sm text-ink/70 file:mr-4 file:rounded-lg file:border-0 file:bg-mint file:px-4 file:py-2 file:text-sm file:font-semibold file:text-club hover:file:bg-grass/20">
                    <p class="mt-1.5 text-sm text-ink/55">JPG, PNG, or WebP, up to 2 MB.</p>
                    @error('avatar')
                        <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>
                    @enderror
                    @if ($user->avatar_path)
                        <label class="mt-2 flex items-center gap-2 text-sm text-ink/70">
                            <input type="checkbox" name="remove_avatar" value="1" class="accent-club"> Remove current photo
                        </label>
                    @endif
                </div>
            </div>

            <div>
                <p class="mb-1.5 text-sm font-medium text-ink/80">Cover photo</p>
                <div class="relative h-28 overflow-hidden rounded-xl bg-club">
                    @if ($user->coverUrl())
                        <img src="{{ $user->coverUrl() }}" alt="Current cover photo" class="size-full object-cover">
                    @else
                        <x-field-backdrop class="absolute inset-0 size-full" />
                        <span class="eyebrow absolute bottom-2 left-3 text-[0.6rem] text-ivory/80">Default: the field</span>
                    @endif
                </div>
                <label for="cover" class="sr-only">Cover photo</label>
                <input id="cover" name="cover" type="file" accept="image/jpeg,image/png,image/webp" data-crop="cover"
                    class="mt-3 block w-full text-sm text-ink/70 file:mr-4 file:rounded-lg file:border-0 file:bg-mint file:px-4 file:py-2 file:text-sm file:font-semibold file:text-club hover:file:bg-grass/20">
                <p class="mt-1.5 text-sm text-ink/55">A wide photo works best: a finish line, your desk, your team. Up to 4 MB.</p>
                @error('cover')
                    <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>
                @enderror
                @if ($user->cover_path)
                    <label class="mt-2 flex items-center gap-2 text-sm text-ink/70">
                        <input type="checkbox" name="remove_cover" value="1" class="accent-club"> Go back to the field
                    </label>
                @endif
            </div>
        </section>

        {{-- About you. --}}
        <section class="space-y-6 rounded-2xl border border-line bg-white p-6 sm:p-8">
            <h2 class="eyebrow text-club">About you</h2>
            <x-form.input name="name" label="Name" :value="$user->name" autocomplete="name" required />
            <x-form.input name="email" label="Email" type="email" :value="$user->email" autocomplete="email" required
                hint="{{ $user->hasVerifiedEmail() ? 'Verified.' : 'Not verified yet.' }} Changing it sends a new verification link." />
            <x-form.input name="username" label="Username" :value="$user->username" maxlength="30" required
                hint="Lowercase letters, numbers, dots, and underscores. Your profile lives at {{ route('profiles.show', $user) }}." />
            <x-form.textarea name="bio" label="Bio (optional)" :value="$user->bio" maxlength="160" hint="Up to 160 characters." />
            <x-form.input name="location" label="Location (optional)" :value="$user->location" maxlength="80" placeholder="Washington, DC" />

            <div>
                <label for="featured_goal_id" class="mb-1.5 block text-sm font-medium text-ink/80">Featured goal (optional)</label>
                <select id="featured_goal_id" name="featured_goal_id" class="block w-full rounded-lg border border-line bg-white px-3.5 py-2.5 text-ink outline-none focus:border-club focus:ring-4 focus:ring-club/10">
                    <option value="">None</option>
                    @foreach ($featurableGoals as $goal)
                        <option value="{{ $goal->id }}" @selected(old('featured_goal_id', $user->featured_goal_id) == $goal->id)>
                            {{ $goal->title }}{{ $goal->isCompleted() ? ' (completed)' : '' }}
                        </option>
                    @endforeach
                </select>
                <p class="mt-1.5 text-sm text-ink/55">Pinned to the top of your profile.</p>
                @error('featured_goal_id')
                    <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>
        </section>

        {{-- Settings. --}}
        <section class="space-y-6 rounded-2xl border border-line bg-white p-6 sm:p-8">
            <div class="flex items-center justify-between gap-4">
                <h2 class="eyebrow text-club">Settings</h2>
                <a href="{{ route('settings.notifications') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-club hover:underline"><x-icon name="bell" class="size-4" /> Notifications &amp; reminders</a>
            </div>

            <div>
                <label for="timezone" class="mb-1.5 block text-sm font-medium text-ink/80">Time zone</label>
                <select id="timezone" name="timezone" class="block w-full rounded-lg border border-line bg-white px-3.5 py-2.5 text-ink outline-none focus:border-club focus:ring-4 focus:ring-club/10">
                    @foreach ($timezones as $region => $zones)
                        <optgroup label="{{ $region }}">
                            @foreach ($zones as $zone)
                                <option value="{{ $zone }}" @selected(old('timezone', $user->timezoneName()) === $zone && ($region === 'Common in the US' || ! in_array($zone, $timezones['Common in the US'], true)))>{{ str_replace('_', ' ', $zone) }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                <button type="button" hidden data-use-browser-timezone="timezone" class="mt-2 text-sm font-semibold text-club hover:underline"></button>
                <p class="mt-1.5 text-sm text-ink/55">Your GoalWeeks run Monday to Sunday on this clock, and check-ins close at midnight here.</p>
                @error('timezone')
                    <p class="mt-1.5 text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>

            <fieldset>
                <legend class="mb-2 block text-sm font-medium text-ink/80">Who can see your profile?</legend>
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
        </section>

        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-end">
            <a href="{{ route('profile.show') }}" class="rounded-lg px-4 py-3 text-center font-semibold text-ink/70 transition hover:text-ink">Cancel</a>
            <x-form.button class="sm:w-auto sm:px-8">Save profile</x-form.button>
        </div>
    </form>

    <div class="mx-auto mt-10 flex max-w-2xl items-center justify-between gap-4 border-t border-line pt-6">
        <p class="text-sm text-ink/55">
            <a href="{{ route('privacy') }}" class="hover:text-club">Privacy</a> &middot; <a href="{{ route('terms') }}" class="hover:text-club">Terms</a>
            @can('admin')
                &middot; <a href="{{ route('admin.index') }}" class="font-semibold text-club hover:underline">Admin</a>
            @endcan
            &middot; Done with GoalLine?
        </p>
        <a href="{{ route('account.delete') }}" class="text-sm font-semibold text-ink/60 hover:text-red-700">Delete account</a>
    </div>
</x-layouts.app>
