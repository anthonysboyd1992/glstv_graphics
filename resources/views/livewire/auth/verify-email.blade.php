<x-layouts::auth :title="__('Email verification')">
    <div class="mt-4 flex flex-col gap-6">
        <p class="text-center text-sm text-zinc-400">
            {{ __('Please verify your email address by clicking on the link we just emailed to you.') }}
        </p>

        @if (session('status') == 'verification-link-sent')
            <p class="text-center text-sm font-medium text-green-400">
                {{ __('A new verification link has been sent to the email address you provided during registration.') }}
            </p>
        @endif

        <div class="flex flex-col items-center space-y-3">
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <x-ui.btn type="submit" variant="primary" class="w-full">{{ __('Resend verification email') }}</x-ui.btn>
            </form>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <x-ui.btn variant="ghost" type="submit" data-test="logout-button">{{ __('Log out') }}</x-ui.btn>
            </form>
        </div>
    </div>
</x-layouts::auth>
