<section class="w-full">
    @include('partials.settings-heading')

    <h2 class="sr-only">{{ __('Appearance settings') }}</h2>

    <x-settings.layout :heading="__('Appearance')" :subheading="__('Update the appearance settings for your account')">
        <div
            x-data="{
                current: localStorage.getItem('appearance') || 'dark',
                set(value) {
                    this.current = value;
                    localStorage.setItem('appearance', value);
                    const dark = value === 'dark' || (value === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                    document.documentElement.classList.toggle('dark', dark);
                },
            }"
            class="inline-flex rounded-lg border border-zinc-700 bg-zinc-900 p-1"
        >
            @foreach ([['light', 'sun', __('Light')], ['dark', 'moon', __('Dark')], ['system', 'computer-desktop', __('System')]] as [$value, $icon, $label])
                <button
                    type="button"
                    @click="set('{{ $value }}')"
                    class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm"
                    :class="current === '{{ $value }}' ? 'bg-zinc-700 text-white' : 'text-zinc-400 hover:text-white'"
                >
                    <x-icon :name="$icon" class="size-4" />
                    {{ $label }}
                </button>
            @endforeach
        </div>
    </x-settings.layout>
</section>
