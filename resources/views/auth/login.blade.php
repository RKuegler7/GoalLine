<x-auth-card title="Welcome back" eyebrow="Back on track" intro="Pick up right where you left off.">
    @if (session('status'))
        <p role="status" class="mb-5 rounded-xl bg-mint px-4 py-3 text-sm font-medium text-club">{{ session('status') }}</p>
    @endif

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <x-form.input name="email" label="Email" type="email" autocomplete="email" required autofocus />
        <x-form.input name="password" label="Password" type="password" autocomplete="current-password" required />

        <label class="flex items-center gap-2 text-sm text-ink/80">
            <input type="checkbox" name="remember" class="size-4 rounded border-line accent-club">
            Keep me logged in
        </label>

        <p class="-mt-2 text-right text-sm"><a href="{{ route('password.request') }}" class="font-semibold text-club hover:underline">Forgot your password?</a></p>

        <x-form.button>Log in</x-form.button>
    </form>

    <p class="mt-6 border-t border-line pt-6 text-center text-sm text-ink/70">
        New to GoalLine?
        <a href="{{ route('register') }}" class="font-semibold text-club hover:underline">Create an account</a>
    </p>
</x-auth-card>
