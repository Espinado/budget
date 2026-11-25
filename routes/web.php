<?php

use App\Http\Controllers\AppVersionController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PrivacyController;
use App\Http\Controllers\FamilyPulseController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\VaultDocumentController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Middleware\EnsureOpeningBalances;
use App\Livewire\Budget\Dashboard;
use App\Livewire\Budget\Debts;
use App\Livewire\Budget\DebtShow;
use App\Livewire\Budget\OpeningBalances;
use App\Livewire\Budget\PropertyObjectForm;
use App\Livewire\Budget\PropertyObjects;
use App\Livewire\Budget\Settings;
use App\Livewire\Budget\Shopping;
use App\Livewire\Budget\Statistics;
use App\Livewire\Budget\Vault;
use Illuminate\Support\Facades\Route;

Route::get('/app-version', AppVersionController::class)->name('app.version');

Route::get('/help', [SupportController::class, 'help'])->name('help');
Route::get('/privacy', PrivacyController::class)->name('privacy');
Route::post('/support', [SupportController::class, 'store'])->middleware('throttle:10,1')->name('support.store');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store']);
    Route::get('/forgot-password', [PasswordResetController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'store'])->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->name('password.update');
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::get('/', HomeController::class)->name('home');

Route::middleware(['auth', EnsureOpeningBalances::class])->group(function () {
    Route::get('/family/pulse', FamilyPulseController::class)->name('family.pulse');
    Route::get('/budget', Dashboard::class)->name('dashboard');
    Route::get('/settings', Settings::class)->name('settings');
    Route::get('/vault', Vault::class)->name('vault');
    Route::get('/vault/documents/{document}', [VaultDocumentController::class, 'show'])->whereNumber('document')->name('vault.documents.show');
    Route::get('/vault/documents/{document}/download', [VaultDocumentController::class, 'download'])->whereNumber('document')->name('vault.documents.download');
    Route::get('/shopping', Shopping::class)->name('shopping');
    Route::get('/objects', PropertyObjects::class)->name('objects.index');
    Route::get('/objects/create', PropertyObjectForm::class)->name('objects.create');
    Route::get('/objects/{propertyObject}', PropertyObjectForm::class)->whereNumber('propertyObject')->name('objects.edit');
    Route::get('/debts', Debts::class)->name('debts');
    Route::get('/debts/{debt}', DebtShow::class)->name('debts.show');
    Route::get('/statistics', Statistics::class)->name('statistics');
    Route::get('/start', OpeningBalances::class)->name('opening')->withoutMiddleware(EnsureOpeningBalances::class);
});
