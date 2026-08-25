<x-layouts::auth :title="__('Register')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Create an account')" :description="__('Enter your details below to create your account')" />

        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-6">
            @csrf
            <x-ui.input name="name" :label="__('Name')" :value="old('name')" type="text" required autofocus autocomplete="name" :placeholder="__('Full name')" />
            <x-ui.input name="email" :label="__('Email address')" :value="old('email')" type="email" required autocomplete="email" placeholder="email@example.com" />
            <x-ui.input name="password" :label="__('Password')" type="password" required autocomplete="new-password" :placeholder="__('Password')" viewable />
            <x-ui.input name="password_confirmation" :label="__('Confirm password')" type="password" required autocomplete="new-password" :placeholder="__('Confirm password')" viewable />

            <x-ui.btn type="submit" variant="primary" class="w-full" data-test="register-user-button">
                {{ __('Create account') }}
            </x-ui.btn>
        </form>

        <p class="text-center text-sm text-zinc-400">
            {{ __('Already have an account?') }}
            <a href="{{ route('login') }}" wire:navigate class="font-medium text-zinc-200 underline hover:text-white">{{ __('Log in') }}</a>
        </p>
    </div>
</x-layouts::auth>
