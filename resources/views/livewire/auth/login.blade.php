<x-layouts::auth :title="__('Log in')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Log in to your account')" :description="__('Enter your email and password below to log in')" />

        <x-auth-session-status class="text-center" :status="session('status')" />

        <x-passkey-verify />

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6">
            @csrf

            <x-ui.input
                name="email"
                :label="__('Email address')"
                :value="old('email')"
                type="email"
                required
                autofocus
                autocomplete="email"
                placeholder="email@example.com"
            />

            <div class="relative">
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" wire:navigate class="absolute top-0 right-0 text-sm text-zinc-400 hover:text-white">
                        {{ __('Forgot your password?') }}
                    </a>
                @endif
                <x-ui.input
                    name="password"
                    :label="__('Password')"
                    type="password"
                    required
                    autocomplete="current-password"
                    :placeholder="__('Password')"
                    viewable
                />
            </div>

            <x-ui.checkbox name="remember" :label="__('Remember me')" :checked="old('remember')" />

            <x-ui.btn variant="primary" type="submit" class="w-full" data-test="login-button">
                {{ __('Log in') }}
            </x-ui.btn>
        </form>

        <p class="text-center text-sm text-zinc-400">
            {{ __('Don\'t have an account?') }}
            <a href="{{ route('register') }}" wire:navigate class="font-medium text-zinc-200 underline hover:text-white">{{ __('Sign up') }}</a>
        </p>
    </div>
</x-layouts::auth>
