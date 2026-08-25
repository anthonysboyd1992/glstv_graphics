<div
    x-data="{
        toasts: [],
        add(event) {
            const toast = {
                id: Date.now() + Math.random(),
                text: event.detail.text,
                variant: event.detail.variant || 'success',
            };

            this.toasts.push(toast);
            setTimeout(() => this.remove(toast.id), 3500);
        },
        remove(id) {
            this.toasts = this.toasts.filter((toast) => toast.id !== id);
        },
    }"
    @toast.window="add($event)"
    class="pointer-events-none fixed inset-x-0 bottom-4 z-[70] flex flex-col items-center gap-2 px-4 sm:items-end sm:px-6"
>
    <template x-for="toast in toasts" :key="toast.id">
        <div
            class="pointer-events-auto w-full max-w-sm rounded-lg border px-4 py-3 text-sm shadow-xl"
            :class="toast.variant === 'warning'
                ? 'border-amber-700 bg-amber-950 text-amber-100'
                : toast.variant === 'danger'
                    ? 'border-red-800 bg-red-950 text-red-100'
                    : 'border-zinc-700 bg-zinc-800 text-zinc-100'"
            x-text="toast.text"
        ></div>
    </template>
</div>
