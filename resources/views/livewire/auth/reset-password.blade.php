<x-layouts::auth :title="__('Reset password')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Reset password')" :description="__('Please enter your new password below')" />

        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('password.update') }}" class="flex flex-col gap-6">
            @csrf
            <input type="hidden" name="token" value="{{ request()->route('token') }}">
            <x-ui.input name="email" value="{{ request('email') }}" :label="__('Email')" type="email" required autocomplete="email" />
            <x-ui.input name="password" :label="__('Password')" type="password" required autocomplete="new-password" :placeholder="__('Password')" viewable />
            <x-ui.input name="password_confirmation" :label="__('Confirm password')" type="password" required autocomplete="new-password" :placeholder="__('Confirm password')" viewable />
            <x-ui.btn type="submit" variant="primary" class="w-full" data-test="reset-password-button">
                {{ __('Reset password') }}
            </x-ui.btn>
        </form>
    </div>
</x-layouts::auth>
