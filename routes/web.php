<?php

use App\Http\Controllers\QuoteController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/oferta');

Route::get('/oferta', [QuoteController::class,
    'create'])->name('oferta.create');
Route::post('/oferta', [QuoteController::class,
    'store'])->name('oferta.store');
Route::get('/oferta/{quoteRequest}', [QuoteController::class,
    'show'])->name('oferta.show');

Route::get('/localitati/{county}', [QuoteController::class,
    'localities'])->name('localitati');
