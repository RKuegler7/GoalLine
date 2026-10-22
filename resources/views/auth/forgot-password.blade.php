<x-auth-card title="Reset your password" eyebrow="Back on track" intro="Enter your email and we'll send you a link to choose a new password.">
    @if (session('status'))
        <p role="status" class="mb-5 rounded-xl bg-mint px-4 py-3 text-sm font-medium text-club">{{ session('status') }}</p>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
        @csrf
        <x-form.input name="email" label="Email" type="email" autocomplete="email" required autofocus />
        <x-form.button>Email me a reset link</x-form.button>
    </form>

    <p class="mt-6 border-t border-line pt-6 text-center text-sm text-ink/70">
        Remembered it?
        <a href="{{ route('login') }}" class="font-semibold text-club hover:underline">Log in</a>
    </p>
</x-auth-card>
