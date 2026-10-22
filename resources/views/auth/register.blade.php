<x-auth-card title="Create your account" eyebrow="Get in line" intro="Start with one goal that matters. We'll help you build the plan.">
    <form method="POST" action="{{ route('register') }}" class="space-y-5">
        @csrf

        <x-form.input name="name" label="Name" autocomplete="name" required autofocus />
        <x-form.input name="email" label="Email" type="email" autocomplete="email" required />
        <x-form.input name="password" label="Password" type="password" autocomplete="new-password" required />
        <x-form.input name="password_confirmation" label="Confirm password" type="password" autocomplete="new-password" required />

        {{-- Filled in by app.js with the browser's time zone. --}}
        <input type="hidden" name="timezone" value="{{ old('timezone') }}" data-detect-timezone>

        <x-form.button>Create account</x-form.button>
    </form>

    <p class="mt-6 border-t border-line pt-6 text-center text-sm text-ink/70">
        Already have an account?
        <a href="{{ route('login') }}" class="font-semibold text-club hover:underline">Log in</a>
    </p>
</x-auth-card>
