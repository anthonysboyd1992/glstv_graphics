<x-layouts::auth :title="__('Forgot password')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Forgot password')" :description="__('Enter your email to receive a password reset link')" />

        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-6">
            @csrf
            <x-ui.input name="email" :label="__('Email address')" type="email" required autofocus placeholder="email@example.com" />
            <x-ui.btn variant="primary" type="submit" class="w-full" data-test="email-password-reset-link-button">
                {{ __('Email password reset link') }}
            </x-ui.btn>
        </form>

        <p class="text-center text-sm text-zinc-400">
            {{ __('Or, return to') }}
            <a href="{{ route('login') }}" wire:navigate class="font-medium text-zinc-200 underline hover:text-white">{{ __('log in') }}</a>
        </p>
    </div>
</x-layouts::auth>
