<section class="w-full">
    @include('partials.settings-heading')

    <h2 class="sr-only">{{ __('Security settings') }}</h2>

    <x-settings.layout :heading="__('Update password')" :subheading="__('Ensure your account is using a long, random password to stay secure')">
        <form method="POST" wire:submit="updatePassword" class="mt-6 space-y-6">
            <x-ui.input wire:model="current_password" :label="__('Current password')" type="password" required autocomplete="current-password" viewable />
            <x-ui.input wire:model="password" :label="__('New password')" type="password" required autocomplete="new-password" viewable />
            <x-ui.input wire:model="password_confirmation" :label="__('Confirm password')" type="password" required autocomplete="new-password" viewable />
            <x-ui.btn variant="primary" type="submit" data-test="update-password-button">{{ __('Save') }}</x-ui.btn>
        </form>

        @if ($canManageTwoFactor)
            <section class="mt-12">
                <h3 class="text-lg font-semibold">{{ __('Two-factor authentication') }}</h3>
                <p class="text-sm text-zinc-400">{{ __('Manage your two-factor authentication settings') }}</p>

                <div class="mt-4 space-y-6 text-sm" wire:cloak>
                    @if ($twoFactorEnabled)
                        <div class="space-y-4">
                            <p class="text-zinc-300">
                                {{ __('You will be prompted for a secure, random pin during login, which you can retrieve from the TOTP-supported application on your phone.') }}
                            </p>
                            <x-ui.btn variant="danger" wire:click="disable">{{ __('Disable 2FA') }}</x-ui.btn>
                            <livewire:settings.two-factor.recovery-codes :$requiresConfirmation />
                        </div>
                    @else
                        <div class="space-y-4">
                            <p class="text-zinc-400">
                                {{ __('When you enable two-factor authentication, you will be prompted for a secure pin during login. This pin can be retrieved from a TOTP-supported application on your phone.') }}
                            </p>
                            <x-ui.btn variant="primary" wire:click="enable">{{ __('Enable 2FA') }}</x-ui.btn>
                        </div>
                    @endif
                </div>
            </section>
        @endif

        @if ($canManageTwoFactor)
            @if ($showModal)
            <x-ui.modal close="showModal" class="max-w-md">
                <div class="space-y-6">
                    <div class="space-y-2 text-center">
                        <h3 class="text-lg font-semibold">{{ $this->modalConfig['title'] }}</h3>
                        <p class="text-sm text-zinc-400">{{ $this->modalConfig['description'] }}</p>
                    </div>

                    @if ($showVerificationStep)
                        <div class="space-y-6">
                            <input
                                type="text"
                                name="code"
                                wire:model="code"
                                inputmode="numeric"
                                maxlength="6"
                                class="mx-auto block w-48 rounded-lg border border-zinc-700 bg-zinc-900 px-3 py-2 text-center font-mono text-xl tracking-[0.4em] outline-none focus:border-zinc-500"
                            />
                            <div class="flex items-center gap-3">
                                <x-ui.btn class="flex-1" wire:click="resetVerification">{{ __('Back') }}</x-ui.btn>
                                <x-ui.btn variant="primary" class="flex-1" wire:click="confirmTwoFactor">{{ __('Confirm') }}</x-ui.btn>
                            </div>
                        </div>
                    @else
                        @error('setupData')
                            <p class="rounded-lg border border-red-800 bg-red-950 px-3 py-2 text-sm text-red-200">{{ $message }}</p>
                        @enderror

                        <div class="flex justify-center">
                            <div class="relative aspect-square w-64 overflow-hidden rounded-lg border border-zinc-700">
                                @empty($qrCodeSvg)
                                    <div class="absolute inset-0 flex items-center justify-center bg-zinc-800">
                                        <span class="size-6 animate-spin rounded-full border-2 border-zinc-600 border-t-white"></span>
                                    </div>
                                @else
                                    <div class="flex h-full items-center justify-center bg-white p-4">
                                        {!! $qrCodeSvg !!}
                                    </div>
                                @endempty
                            </div>
                        </div>

                        <x-ui.btn :disabled="$errors->has('setupData')" variant="primary" class="w-full" wire:click="showVerificationIfNecessary">
                            {{ $this->modalConfig['buttonText'] }}
                        </x-ui.btn>

                        <div class="space-y-3">
                            <p class="text-center text-sm text-zinc-500">{{ __('or, enter the code manually') }}</p>
                            <div
                                class="flex items-stretch overflow-hidden rounded-xl border border-zinc-700"
                                x-data="{
                                    copied: false,
                                    async copy() {
                                        try {
                                            await navigator.clipboard.writeText('{{ $manualSetupKey }}');
                                            this.copied = true;
                                            setTimeout(() => this.copied = false, 1500);
                                        } catch (e) {}
                                    }
                                }"
                            >
                                @empty($manualSetupKey)
                                    <div class="flex w-full items-center justify-center p-3">
                                        <span class="size-4 animate-spin rounded-full border-2 border-zinc-600 border-t-white"></span>
                                    </div>
                                @else
                                    <input type="text" readonly value="{{ $manualSetupKey }}" class="w-full bg-transparent p-3 outline-none" />
                                    <button type="button" @click="copy()" class="border-l border-zinc-700 px-3 text-zinc-400 hover:text-white">
                                        <span x-show="!copied"><x-icon name="document-duplicate" /></span>
                                        <span x-show="copied" x-cloak><x-icon name="check" class="text-green-400" /></span>
                                    </button>
                                @endempty
                            </div>
                        </div>
                    @endif
                </div>
            </x-ui.modal>
            @endif
        @endif

        @if ($canManagePasskeys)
            <section class="mt-12">
                <h3 class="text-lg font-semibold">{{ __('Passkeys') }}</h3>
                <p class="text-sm text-zinc-400">{{ __('Manage your passkeys for passwordless sign-in') }}</p>

                <div class="mt-6 space-y-6 text-sm" wire:cloak>
                    <div class="overflow-hidden rounded-lg border border-zinc-800">
                        @forelse ($passkeys as $passkey)
                            <div class="flex items-center justify-between p-4 {{ ! $loop->last ? 'border-b border-zinc-800' : '' }}">
                                <div class="flex items-center gap-4">
                                    <div class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-zinc-800">
                                        <x-icon name="key" class="size-5 text-zinc-400" />
                                    </div>
                                    <div class="space-y-1">
                                        <div class="flex items-center gap-2.5">
                                            <p class="font-medium">{{ $passkey['name'] }}</p>
                                            @if ($passkey['authenticator'])
                                                <x-ui.badge>{{ $passkey['authenticator'] }}</x-ui.badge>
                                            @endif
                                        </div>
                                        <p class="text-xs text-zinc-500">
                                            {{ __('Added :time', ['time' => $passkey['created_at_diff']]) }}
                                            @if ($passkey['last_used_at_diff'])
                                                <span class="mx-1 opacity-50">/</span>
                                                {{ __('Last used :time', ['time' => $passkey['last_used_at_diff']]) }}
                                            @endif
                                        </p>
                                    </div>
                                </div>
                                <x-ui.btn variant="danger" size="sm" icon="trash" wire:click="confirmDelete({{ $passkey['id'] }})" />
                            </div>
                        @empty
                            <div class="p-8 text-center">
                                <div class="mx-auto mb-4 flex size-14 items-center justify-center rounded-2xl bg-zinc-800">
                                    <x-icon name="key" class="size-7 text-zinc-500" />
                                </div>
                                <p class="font-medium">{{ __('No passkeys yet') }}</p>
                                <p class="mt-1 text-zinc-400">{{ __('Add a passkey to sign in without a password') }}</p>
                            </div>
                        @endforelse
                    </div>
                    <x-passkey-registration />
                </div>
            </section>
        @endif
    </x-settings.layout>

    @if ($showDeleteModal)
    <x-ui.modal close="showDeleteModal" class="max-w-md">
        <div class="space-y-6">
            <div class="space-y-2">
                <h3 class="text-lg font-semibold">{{ __('Remove passkey') }}</h3>
                <p class="text-sm text-zinc-400">
                    {{ __('Are you sure you want to remove the passkey ":name"? You will no longer be able to use it to sign in.', ['name' => $deletingPasskeyName]) }}
                </p>
            </div>
            <div class="flex justify-end gap-3">
                <x-ui.btn wire:click="closeDeleteModal">{{ __('Cancel') }}</x-ui.btn>
                <x-ui.btn variant="danger" wire:click="deletePasskey">{{ __('Remove passkey') }}</x-ui.btn>
            </div>
        </div>
    </x-ui.modal>
    @endif
</section>
