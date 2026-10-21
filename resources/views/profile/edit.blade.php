<x-layouts.app title="Edit profile · GoalLine">
    <x-slot:hero>
        <div class="mx-auto max-w-5xl px-4 pt-6 pb-12">
            <a href="{{ route('profile.show') }}" class="text-sm font-medium text-ivory/75 hover:text-ivory">&larr; Profile</a>
            <h1 class="mt-6 font-serif text-4xl font-medium tracking-tight text-ivory sm:text-5xl">Edit profile</h1>
        </div>
    </x-slot:hero>

    <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data"
        class="mx-auto max-w-2xl space-y-6 rounded-2xl border border-line bg-white p-6 sm:p-10">
        @csrf
        @method('PUT')

        <fieldset class="flex items-center gap-5">
            <legend class="sr-only">Profile photo</legend>
            <x-avatar :user="$user" class="size-20 text-2xl" />
            <div class="min-w-0 flex-1">
                <label for="avatar" class="mb-1.5 block text-sm font-medium text-ink/80">Profile photo</label>
                <input id="avatar" name="avatar" type="file" accept="image/jpeg,image/png,image/webp"
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
        </fieldset>

        <x-form.input name="name" label="Name" :value="$user->name" autocomplete="name" required />
        <x-form.input name="username" label="Username" :value="$user->username" maxlength="30" required
            hint="Lowercase letters, numbers, dots, and underscores. Friends and league mates will find you by this." />
        <x-form.textarea name="bio" label="Bio (optional)" :value="$user->bio" maxlength="160" hint="Up to 160 characters." />
        <x-form.input name="location" label="Location (optional)" :value="$user->location" maxlength="80" placeholder="Washington, DC" />

        <div class="flex flex-col-reverse gap-3 border-t border-line pt-6 sm:flex-row sm:items-center sm:justify-end">
            <a href="{{ route('profile.show') }}" class="rounded-lg px-4 py-3 text-center font-semibold text-ink/70 transition hover:text-ink">Cancel</a>
            <x-form.button class="sm:w-auto sm:px-8">Save profile</x-form.button>
        </div>
    </form>
</x-layouts.app>
