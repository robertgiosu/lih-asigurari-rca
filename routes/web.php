<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\PolicyController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QuoteController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HistoryController;

Route::redirect('/', '/oferta');

// Cotatie
Route::get('/oferta', [QuoteController::class, 'create'])->name('oferta.create');
Route::post('/oferta', [QuoteController::class, 'store'])->name('oferta.store');
Route::get('/oferta/{quoteRequest}', [QuoteController::class, 'show'])->name('oferta.show');
Route::get('/oferta/{quoteRequest}/pdf/{offer}', [QuoteController::class, 'offerPdf'])->name('oferta.pdf');

// Polita
Route::post('/oferta/{quoteRequest}/emite/{offer}', [PolicyController::class, 'store'])->name('polita.store');
Route::get('/polita/{policy}', [PolicyController::class, 'show'])->name('polita.show');
Route::get('/polita/{policy}/pdf', [PolicyController::class, 'pdf'])->name('polita.pdf');

// Alimenteaza dropdown-ul de localitati din formular
Route::get('/localitati/{county}', [QuoteController::class, 'localities'])->name('localitati');

// Conturi
Route::middleware('guest')->group(function () {
    Route::get('/inregistrare', [RegisterController::class,
        'create'])->name('register');
    Route::post('/inregistrare', [RegisterController::class, 'store']);

    Route::get('/autentificare', [LoginController::class,
        'create'])->name('login');
    Route::post('/autentificare', [LoginController::class,
        'store'])->middleware('throttle:5,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/deconectare', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/profil', [ProfileController::class, 'edit'])->name('profil.edit');
    Route::put('/profil', [ProfileController::class, 'update'])->name('profil.update');
    Route::get('/istoric', [HistoryController::class,
        'index'])->name('istoric.index');
    Route::get('/istoric/{quoteRequest}', [HistoryController::class,
        'show'])->name('istoric.show');
});
