{{-- Vorschau + Testmail. Läuft im Alpine-Kontext von form.blade.php und wird
     von BEIDEN Modi benutzt: im Baukasten unter den Bausteinen, im Code-Modus
     als dritter Reiter (wie in der Vorlagenverwaltung). --}}
<div class="rounded-xl border border-gray-200 bg-white p-2">
    <div class="mb-2 border-b border-gray-100 px-2 py-1 text-sm text-gray-500">
        Betreff: <span class="font-medium text-gray-700" x-text="vorschauBetreff || '—'"></span>
    </div>
    <iframe x-ref="vorschau" class="h-[36rem] w-full rounded" title="Vorschau"></iframe>
</div>

<div class="mt-4 rounded-xl border border-gray-200 bg-gray-50 p-4">
    <div class="mb-1 text-sm font-medium text-gray-700">Testmail versenden</div>
    <p class="mb-3 text-xs text-gray-500">
        Schickt die Ausgabe so, wie sie gerade im Formular steht – auch ungespeichert.
        Platzhalter werden dabei mit deinen eigenen Daten gefüllt.
    </p>
    <div class="flex flex-wrap items-center gap-2">
        <input type="email" x-model="testEmail" placeholder="empfaenger@example.org"
               class="w-64 rounded-lg border-gray-300 text-sm">
        <button type="button" @click="testSenden" :disabled="testLaeuft"
                class="inline-flex items-center gap-1.5 rounded-lg border border-indigo-600 px-3 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-50 disabled:opacity-50">
            <i class='bx bx-mail-send'></i>
            <span x-text="testLaeuft ? 'Sende…' : 'Testmail senden'"></span>
        </button>
        <span class="text-sm" :class="testOk ? 'text-emerald-600' : 'text-red-600'" x-text="testMeldung"></span>
    </div>
</div>
