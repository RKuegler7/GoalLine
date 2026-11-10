{{-- A verification link that didn't work (expired, already used, or changed on the way): never a raw 403. --}}
<x-auth-card title="That link didn't work" eyebrow="No problem" intro="It may have expired or been opened in a different place. Let's get you a fresh one.">
    @if ($expired)
        <p class="mb-4 rounded-xl bg-gold/15 px-4 py-3 text-sm text-ink">This verification link has expired. New links work for {{ config('goalline.verification_link_hours') }} hours.</p>
    @endif

    @if ($signedInUnverified)
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button type="submit" class="w-full rounded-full bg-club px-5 py-3 font-semibold text-ivory hover:bg-club-dark">Send me a new verification email</button>
        </form>
    @else
        <form method="POST" action="{{ route('verification.resend') }}" class="space-y-4">
            @csrf
            <x-form.input name="email" label="Your email" type="email" autocomplete="email" :value="old('email', $email)" required />
            <button type="submit" class="w-full rounded-full bg-club px-5 py-3 font-semibold text-ivory hover:bg-club-dark">Send me a new verification email</button>
        </form>
    @endif

    <p class="mt-5 text-center text-sm text-ink/70">
        <a href="{{ route('login') }}" class="font-semibold text-club hover:underline">Back to log in</a>
    </p>
</x-auth-card>
