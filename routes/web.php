<?php

use App\Livewire\Assets\Library as AssetLibrary;
use App\Livewire\Layouts\Editor as LayoutEditor;
use App\Livewire\Layouts\Index as Layouts;
use App\Livewire\Shows\Board;
use App\Livewire\Shows\Cues;
use App\Livewire\Shows\Index as Shows;
use App\Livewire\Users\Index as Users;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::livewire('shows', Shows::class)->name('shows.index');
    Route::livewire('shows/{show}/board', Board::class)->name('shows.board');
    Route::livewire('shows/{show}/cues', Cues::class)->name('shows.cues');

    Route::livewire('layouts', Layouts::class)->name('layouts.index');
    Route::livewire('layouts/{layout}', LayoutEditor::class)->name('layouts.edit');

    Route::livewire('assets', AssetLibrary::class)->name('assets.library');
    Route::livewire('users', Users::class)->middleware('can:users.manage')->name('users.index');
});

require __DIR__.'/settings.php';
