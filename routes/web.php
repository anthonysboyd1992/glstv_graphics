<?php

use App\Livewire\Assets\Library as AssetLibrary;
use App\Livewire\Packs\Index as Packs;
use App\Livewire\Shows\Board;
use App\Livewire\Shows\Index as Shows;
use App\Livewire\Shows\Rundown;
use App\Livewire\Templates\Index as Templates;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::livewire('shows', Shows::class)->name('shows.index');
    Route::livewire('shows/{show}/board', Board::class)->name('shows.board');
    Route::livewire('shows/{show}/rundown', Rundown::class)->name('shows.rundown');

    Route::livewire('assets', AssetLibrary::class)->name('assets.library');
    Route::livewire('packs', Packs::class)->name('packs.index');
    Route::livewire('templates', Templates::class)->name('templates.index');
});

require __DIR__.'/settings.php';
