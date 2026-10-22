<x-auth-card title="Choose a new password" eyebrow="Back on track" intro="Pick something at least 8 characters long.">
    <form method="POST" action="{{ route('password.store') }}" class="space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-form.input name="email" label="Email" type="email" :value="$email" autocomplete="email" required />
        <x-form.input name="password" label="New password" type="password" autocomplete="new-password" required autofocus />
        <x-form.input name="password_confirmation" label="Confirm new password" type="password" autocomplete="new-password" required />
        <x-form.button>Save new password</x-form.button>
    </form>
</x-auth-card>
