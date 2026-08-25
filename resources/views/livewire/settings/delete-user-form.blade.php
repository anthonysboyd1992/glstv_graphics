<section class="mt-10 space-y-6">
    <div class="relative mb-5">
        <h2 class="text-lg font-semibold">{{ __('Delete account') }}</h2>
        <p class="text-sm text-zinc-400">{{ __('Delete your account and all of its resources') }}</p>
    </div>

    <x-ui.btn variant="danger" wire:click="$set('confirming', true)">
        {{ __('Delete account') }}
    </x-ui.btn>

    @if ($confirming)
    <x-ui.modal close="confirming">
        <form method="POST" wire:submit="deleteUser" class="space-y-6">
            <div>
                <h2 class="text-lg font-semibold">{{ __('Are you sure you want to delete your account?') }}</h2>
                <p class="mt-2 text-sm text-zinc-400">
                    {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.') }}
                </p>
            </div>

            <x-ui.input wire:model="password" :label="__('Password')" type="password" viewable />

            <div class="flex justify-end gap-2">
                <x-ui.btn variant="ghost" type="button" wire:click="$set('confirming', false)">{{ __('Cancel') }}</x-ui.btn>
                <x-ui.btn variant="danger" type="submit">{{ __('Delete account') }}</x-ui.btn>
            </div>
        </form>
    </x-ui.modal>
    @endif
</section>
