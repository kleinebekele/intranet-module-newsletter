@php
    $neu = ! $vorlage->exists;
    $ziel = $neu ? route('module.newsletter.vorlagen.store') : route('module.newsletter.vorlagen.update', $vorlage);
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold text-gray-800">
                {{ $neu ? 'Neue Mailvorlage' : 'Mailvorlage bearbeiten' }}
            </h1>
            <a href="{{ route('module.newsletter.vorlagen.index') }}"
               class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700">
                <x-module-icon name="back" class="text-base" />
                Zurück
            </a>
        </div>
    </x-slot>

    {{-- Kein Kommentar mit Anführungszeichen INNERHALB des x-data-Attributs:
         ein gerades " beendet das Attribut (siehe form.blade.php). --}}
    <div x-data="vorlagenEditor({
            vorschauUrl: @js(route('module.newsletter.vorlagen.vorschau')),
            csrf: @js(csrf_token()),
         })">

        <form method="POST" action="{{ $ziel }}">
            @csrf
            @unless ($neu) @method('PUT') @endunless

            <div class="mb-6 grid gap-4 lg:grid-cols-3">
                <section class="rounded-xl border border-gray-200 bg-white p-5 lg:col-span-2">
                    <label for="name" class="block text-sm font-medium text-gray-700">Name</label>
                    <input id="name" name="name" type="text" required maxlength="120"
                           value="{{ old('name', $vorlage->name) }}"
                           placeholder="Elternbrief"
                           class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <p class="mt-1 text-xs text-gray-500">So heißt die Vorlage in der Auswahl beim Anlegen einer Ausgabe.</p>
                    @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror

                    <label class="mt-4 flex items-start gap-2 text-sm">
                        <input type="checkbox" name="ist_standard" value="1"
                               @checked(old('ist_standard', $vorlage->ist_standard))
                               class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <span>
                            <span class="font-medium text-gray-800">Standard für neue Ausgaben</span>
                            <span class="block text-xs text-gray-500">
                                Neue Ausgaben starten mit dieser Vorlage. Es gibt höchstens eine Standardvorlage.
                            </span>
                        </span>
                    </label>
                </section>

                <section class="rounded-xl border border-gray-200 bg-gray-50 p-5">
                    <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Platzhalter (Klick fügt an der Cursorposition ein)</div>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($platzhalter as $name => $erklaerung)
                            @php($marke = '{'.'{ '.$name.' }'.'}')
                            <button type="button" @click="einfuegen(@js($marke))" title="{{ $erklaerung }}"
                                    class="rounded-lg border border-gray-300 bg-white px-2 py-1 font-mono text-xs text-gray-700 hover:border-indigo-400 hover:text-indigo-700">
                                {{ $marke }}
                            </button>
                        @endforeach
                    </div>
                    <p class="mt-3 text-xs text-gray-500">
                        <code>{{ '{'.'{ inhalt }'.'}' }}</code> muss vorkommen – dort landet die Ausgabe samt Anrede.
                    </p>
                </section>
            </div>

            {{-- Reiter --}}
            <div class="border-b border-gray-200">
                <nav class="-mb-px flex gap-6">
                    @foreach (['html' => 'HTML-Quelltext', 'text' => 'Reiner Text', 'vorschau' => 'Vorschau'] as $schluessel => $beschriftung)
                        <button type="button" @click="reiter = '{{ $schluessel }}'"
                                class="border-b-2 px-1 py-3 text-sm font-medium"
                                :class="reiter === '{{ $schluessel }}'
                                    ? 'border-indigo-600 text-indigo-700'
                                    : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'">
                            {{ $beschriftung }}
                        </button>
                    @endforeach
                </nav>
            </div>

            <div x-show="reiter === 'html'" class="pt-4">
                {{-- Ein Rahmen ist ein vollständiges HTML-Dokument. Ein Formatier-Feld
                     würde <!DOCTYPE> und <html> beim Speichern verwerfen – deshalb
                     wie im Core nur der Quelltext. --}}
                <p class="mb-2 flex items-start gap-1.5 text-xs text-gray-500">
                    <i class='bx bx-info-circle mt-0.5'></i>
                    <span>
                        Der Rahmen ist ein vollständiges HTML-Dokument – deshalb gibt es hier nur den Quelltext.
                        Tabellenbasiertes HTML mit Inline-Styles sieht in Outlook, Gmail &amp; Co. verlässlich gleich aus.
                    </span>
                </p>
                <textarea name="html" x-ref="feldHtml" x-model="html" @input="nachVorschau" spellcheck="false" required
                          class="block h-[32rem] w-full rounded-lg border-gray-300 font-mono text-xs"></textarea>
                @error('html') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div x-show="reiter === 'text'" class="pt-4">
                <label class="mb-1 block text-sm font-medium text-gray-700">Reiner Text (ohne Formatierung)</label>
                <p class="mb-2 text-xs text-gray-500">
                    Geht als zweite Spur mit und wird angezeigt, wenn ein Mailprogramm kein HTML darstellt.
                    Leer = die Textfassung des mitgelieferten Newsletter-Rahmens.
                </p>
                <textarea name="text" x-ref="feldText" x-model="text" @input="nachVorschau" spellcheck="false"
                          class="block h-[32rem] w-full rounded-lg border-gray-300 font-mono text-xs"></textarea>
            </div>

            <div x-show="reiter === 'vorschau'" class="pt-4">
                <p class="mb-2 text-xs text-gray-500">
                    Mit einer Beispiel-Ausgabe darin – so sähe die fertige Mail aus. Betreff:
                    <span class="font-medium text-gray-700" x-text="vorschauBetreff"></span>
                </p>
                <div class="rounded-xl border border-gray-200 bg-white p-2">
                    <iframe x-ref="vorschau" class="h-[36rem] w-full rounded" title="Vorschau"></iframe>
                </div>
            </div>

            <div class="mt-6 flex items-center gap-3 border-t border-gray-200 pt-4">
                <button type="submit"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    <i class='bx bx-save'></i> Speichern
                </button>
                <a href="{{ route('module.newsletter.vorlagen.index') }}" class="text-sm text-gray-500 hover:text-gray-700">Abbrechen</a>
            </div>
        </form>
    </div>

    @push('scripts')
    <script>
        function vorlagenEditor(config) {
            return {
                reiter: 'html',
                html: @js(old('html', $vorlage->html ?? '')),
                text: @js(old('text', $vorlage->text ?? '')),
                vorschauBetreff: '',
                _timer: null,
                _lauf: 0,
                // Elementbezug EINMAL merken: $refs ist in setTimeout-/await-
                // Callbacks nicht verfügbar (siehe Core-Editor).
                _vorschau: null,
                _feldHtml: null,
                _feldText: null,

                init() {
                    this._vorschau = this.$refs.vorschau;
                    this._feldHtml = this.$refs.feldHtml;
                    this._feldText = this.$refs.feldText;
                    this.nachVorschau();
                },

                // Platzhalter an der Cursorposition des gerade offenen Feldes
                // einfügen (HTML-Quelltext oder Reiner Text). Im Vorschau-Reiter
                // gibt es kein Feld – dann in die Zwischenablage.
                einfuegen(marke) {
                    const feld = this.reiter === 'html' ? this._feldHtml
                               : this.reiter === 'text' ? this._feldText
                               : null;

                    if (! feld) {
                        navigator.clipboard?.writeText(marke);
                        return;
                    }

                    const von = feld.selectionStart ?? feld.value.length;
                    const bis = feld.selectionEnd ?? von;
                    const neu = feld.value.slice(0, von) + marke + feld.value.slice(bis);

                    if (this.reiter === 'html') this.html = neu; else this.text = neu;

                    // Cursor hinter den eingefügten Platzhalter setzen und die Stelle
                    // ins Bild scrollen – erst nach dem Rendern (sonst überschreibt
                    // Alpine die Auswahl) und nach dem Klick (sonst holt sich der
                    // Knopf den Fokus zurück). War das Feld noch nie fokussiert, fügt
                    // der Browser am Ende ein – ohne Scrollen sähe man davon nichts.
                    const pos = von + marke.length;
                    this.$nextTick(() => setTimeout(() => {
                        feld.focus();
                        feld.setSelectionRange(pos, pos);
                        const zeile = neu.slice(0, pos).split('\n').length;
                        const zeilen = neu.split('\n').length || 1;
                        feld.scrollTop = Math.max(0, (zeile / zeilen) * feld.scrollHeight - feld.clientHeight / 2);
                    }, 0));
                    this.nachVorschau();
                },

                nachVorschau() {
                    clearTimeout(this._timer);
                    this._timer = setTimeout(() => this.vorschauLaden(), 350);
                },

                async vorschauLaden() {
                    const lauf = ++this._lauf;

                    try {
                        const res = await fetch(config.vorschauUrl, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': config.csrf },
                            body: JSON.stringify({ html: this.html, text: this.text }),
                        });
                        const daten = await res.json();

                        if (lauf !== this._lauf) return;

                        this.vorschauBetreff = daten.betreff;
                        this._vorschau.srcdoc = daten.html;
                    } catch (e) { /* Vorschau ist unkritisch */ }
                },
            };
        }
    </script>
    @endpush
</x-app-layout>
