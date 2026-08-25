<x-layouts::auth :title="__('Two-factor authentication')">
    <div class="flex flex-col gap-6">
        <div
            class="relative w-full"
            x-cloak
            x-data="{
                showRecoveryInput: @js($errors->has('recovery_code')),
                code: '',
                recovery_code: '',
                toggleInput() {
                    this.showRecoveryInput = ! this.showRecoveryInput;
                    this.code = '';
                    this.recovery_code = '';
                    this.$nextTick(() => {
                        this.showRecoveryInput
                            ? this.$refs.recovery_code?.focus()
                            : this.$refs.otp?.focus();
                    });
                },
            }"
        >
            <div x-show="!showRecoveryInput">
                <x-auth-header
                    :title="__('Authentication code')"
                    :description="__('Enter the authentication code provided by your authenticator application.')"
                />
            </div>
            <div x-show="showRecoveryInput">
                <x-auth-header
                    :title="__('Recovery code')"
                    :description="__('Please confirm access to your account by entering one of your emergency recovery codes.')"
                />
            </div>

            <form method="POST" action="{{ route('two-factor.login.store') }}" class="mt-6">
                @csrf
                <div class="space-y-5 text-center">
                    <div x-show="!showRecoveryInput">
                        <input
                            x-ref="otp"
                            x-model="code"
                            type="text"
                            name="code"
                            inputmode="numeric"
                            autocomplete="one-time-code"
                            maxlength="6"
                            class="mx-auto block w-48 rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-center font-mono text-xl tracking-[0.4em] outline-none focus:border-zinc-500"
                        />
                    </div>
                    <div x-show="showRecoveryInput">
                        <x-ui.input
                            type="text"
                            name="recovery_code"
                            x-ref="recovery_code"
                            x-bind:required="showRecoveryInput"
                            autocomplete="one-time-code"
                            x-model="recovery_code"
                        />
                        @error('recovery_code')
                            <p class="mt-2 text-sm text-red-400">{{ $message }}</p>
                        @enderror
                    </div>
                    <x-ui.btn variant="primary" type="submit" class="w-full">{{ __('Continue') }}</x-ui.btn>
                </div>
                <p class="mt-5 text-center text-sm text-zinc-400">
                    {{ __('or you can') }}
                    <button type="button" class="font-medium underline" @click="toggleInput()">
                        <span x-show="!showRecoveryInput">{{ __('login using a recovery code') }}</span>
                        <span x-show="showRecoveryInput">{{ __('login using an authentication code') }}</span>
                    </button>
                </p>
            </form>
        </div>
    </div>
</x-layouts::auth>
