<div
    class="space-y-6 rounded-xl border border-zinc-800 py-6 shadow-sm"
    wire:cloak
    x-data="{ showRecoveryCodes: false }"
>
    <div class="space-y-2 px-6">
        <div class="flex items-center gap-2">
            <x-icon name="lock-closed" class="size-4" />
            <h3 class="text-lg font-semibold">{{ __('2FA recovery codes') }}</h3>
        </div>
        <p class="text-sm text-zinc-400">
            {{ __('Recovery codes let you regain access if you lose your 2FA device. Store them in a secure password manager.') }}
        </p>
    </div>

    <div class="px-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <x-ui.btn variant="primary" icon="eye" x-show="!showRecoveryCodes" @click="showRecoveryCodes = true">
                {{ __('View recovery codes') }}
            </x-ui.btn>
            <x-ui.btn variant="primary" icon="eye-slash" x-show="showRecoveryCodes" x-cloak @click="showRecoveryCodes = false">
                {{ __('Hide recovery codes') }}
            </x-ui.btn>
            @if (filled($recoveryCodes))
                <x-ui.btn icon="arrow-path" x-show="showRecoveryCodes" x-cloak wire:click="regenerateRecoveryCodes">
                    {{ __('Regenerate codes') }}
                </x-ui.btn>
            @endif
        </div>

        <div x-show="showRecoveryCodes" x-cloak x-transition class="mt-3 space-y-3">
            @error('recoveryCodes')
                <p class="rounded-lg border border-red-800 bg-red-950 px-3 py-2 text-sm text-red-200">{{ $message }}</p>
            @enderror

            @if (filled($recoveryCodes))
                <div class="grid gap-1 rounded-lg bg-zinc-900 p-4 font-mono text-sm" role="list">
                    @foreach ($recoveryCodes as $code)
                        <div role="listitem" class="select-text">{{ $code }}</div>
                    @endforeach
                </div>
                <p class="text-xs text-zinc-500">
                    {{ __('Each recovery code can be used once to access your account and will be removed after use. If you need more, click Regenerate codes above.') }}
                </p>
            @endif
        </div>
    </div>
</div>
