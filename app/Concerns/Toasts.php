<?php

namespace App\Concerns;

trait Toasts
{
    protected function toast(string $text, string $variant = 'success'): void
    {
        $this->dispatch('toast', text: $text, variant: $variant);
    }
}
