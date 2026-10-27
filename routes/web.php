<?php

use Illuminate\Support\Facades\Route;
use Intranet\Modules\Newsletter\Http\Controllers\GruppeController;
use Intranet\Modules\Newsletter\Http\Controllers\NewsletterController;
use Intranet\Modules\Newsletter\Http\Controllers\VorlageController;

/*
 | Routen des Newsletter-Moduls.
 |
 | Konvention (siehe MODULES.md des Core):
 |  - URL-Präfix:  modules/newsletter
 |  - Namen:       module.newsletter.*
 |  - Middleware:  'web' + 'auth'
 |
 | Wer was darf, folgt den Zugriffsstufen der Menüpunkte (Verwaltung → Module):
 | Die Routen hier sind im Manifest per gehoertZu() an „Ausgaben", „Neue
 | Ausgabe" bzw. „Mailvorlagen" gebunden. Freigeben und „an alle" verlangen
 | verwalten auf „Ausgaben".
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
        // Gedrosselt: zehn Testmails je Minute und Benutzer reichen zum Ausprobieren.
        Route::post('/testmail', [NewsletterController::class, 'testmail'])->middleware('throttle:10,1')->name('testmail');
        Route::post('/bild', [NewsletterController::class, 'bild'])->name('bild');

        // Persönliche Zielgruppen (Modal im Ausgabe-Formular, JSON). Vor {kampagne}.
        Route::prefix('gruppen')->name('gruppen.')->group(function () {
            Route::get('/benutzer', [GruppeController::class, 'benutzerSuche'])->name('benutzer');
            Route::post('/', [GruppeController::class, 'store'])->name('store');
            Route::put('/{gruppe}', [GruppeController::class, 'update'])->name('update');
            Route::delete('/{gruppe}', [GruppeController::class, 'destroy'])->name('destroy');
        });

        // Eigene Mailvorlagen (Rahmen) der Redaktion – Menüpunkt „Mailvorlagen".
        // Ebenfalls vor {kampagne}, damit „vorlagen" keine Ausgaben-ID wird.
        // Der Namenspräfix `vorlagen.` hängt alle Unterseiten an den Menüpunkt
        // `vorlagen.index` (Zugriffsprüfung des Core läuft über den Routennamen).
        Route::prefix('vorlagen')->name('vorlagen.')->group(function () {
            Route::get('/', [VorlageController::class, 'index'])->name('index');
            Route::get('/anlegen', [VorlageController::class, 'create'])->name('create');
            Route::post('/', [VorlageController::class, 'store'])->name('store');
            Route::post('/vorschau', [VorlageController::class, 'vorschau'])->name('vorschau');
            Route::get('/{vorlage}/bearbeiten', [VorlageController::class, 'edit'])->name('edit');
            Route::put('/{vorlage}', [VorlageController::class, 'update'])->name('update');
            Route::delete('/{vorlage}', [VorlageController::class, 'destroy'])->name('destroy');
            Route::post('/{vorlage}/standard', [VorlageController::class, 'standard'])->name('standard');
        });

        Route::get('/{kampagne}', [NewsletterController::class, 'show'])->name('show');
        Route::get('/{kampagne}/vorschau', [NewsletterController::class, 'mailVorschau'])->name('mail-vorschau');
        Route::get('/{kampagne}/empfaenger', [NewsletterController::class, 'empfaenger'])->name('empfaenger');
        Route::get('/{kampagne}/bearbeiten', [NewsletterController::class, 'edit'])->name('edit');
        Route::put('/{kampagne}', [NewsletterController::class, 'update'])->name('update');
        Route::delete('/{kampagne}', [NewsletterController::class, 'destroy'])->name('destroy');
        Route::post('/{kampagne}/freigeben', [NewsletterController::class, 'freigeben'])->name('freigeben');
        // Nachträglich an eine frei gewählte Adresse – gedrosselt wie die Testmail.
        Route::post('/{kampagne}/einzelversand', [NewsletterController::class, 'einzelversand'])->middleware('throttle:10,1')->name('einzelversand');
    });
