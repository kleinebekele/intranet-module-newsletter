<?php

use Illuminate\Support\Facades\Route;
use Intranet\Modules\Newsletter\Http\Controllers\NewsletterController;

/*
 | Routen des Newsletter-Moduls.
 |
 | Konvention (siehe MODULES.md des Core):
 |  - URL-Präfix:  modules/newsletter
 |  - Namen:       module.newsletter.*
 |  - Middleware:  'web' + 'auth'
 |
 | Wer das Modul sehen darf, darf auch schreiben und freigeben. Das steuert die
 | Rollen-Sichtbarkeit des Moduls in der Modulverwaltung – für eine eigene
 | „Redaktion"-Rolle legt man dort einfach eine an und gibt nur ihr das Modul frei.
*/
Route::middleware(['web', 'auth'])
    ->prefix('modules/newsletter')
    ->name('module.newsletter.')
    ->group(function () {
        Route::get('/', [NewsletterController::class, 'index'])->name('index');
        Route::get('/anlegen', [NewsletterController::class, 'create'])->name('create');
        Route::post('/', [NewsletterController::class, 'store'])->name('store');

        // Technische Endpunkte des Editors – vor der {kampagne}-Route, damit
        // „reichweite" nicht als Ausgaben-ID gelesen wird.
        Route::post('/reichweite', [NewsletterController::class, 'reichweite'])->name('reichweite');
        Route::post('/vorschau', [NewsletterController::class, 'vorschau'])->name('vorschau');
        Route::post('/testmail', [NewsletterController::class, 'testmail'])->name('testmail');
        Route::post('/bild', [NewsletterController::class, 'bild'])->name('bild');

        Route::get('/{kampagne}', [NewsletterController::class, 'show'])->name('show');
        Route::get('/{kampagne}/bearbeiten', [NewsletterController::class, 'edit'])->name('edit');
        Route::put('/{kampagne}', [NewsletterController::class, 'update'])->name('update');
        Route::delete('/{kampagne}', [NewsletterController::class, 'destroy'])->name('destroy');
        Route::post('/{kampagne}/freigeben', [NewsletterController::class, 'freigeben'])->name('freigeben');
    });
