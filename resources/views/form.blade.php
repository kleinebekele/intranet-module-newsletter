@php
    $neu = ! $kampagne->exists;
    $ziel = $neu ? route('module.newsletter.store') : route('module.newsletter.update', $kampagne);
    $gewaehlt = old('zielgruppen', $kampagne->zielgruppen ?? []);
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold text-gray-800">
                {{ $neu ? 'Neue Ausgabe' : 'Ausgabe bearbeiten' }}
            </h1>
            <a href="{{ route('module.newsletter.index') }}"
               class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700">
                <x-module-icon name="back" class="text-base" />
                Zurück
            </a>
        </div>
    </x-slot>

    <div x-data="newsletterEditor({
            bausteine: @js($kampagne->bausteine ?? []),
            modus: @js(old('modus', $kampagne->modus ?? 'bausteine')),
            mitRahmen: @js((string) (int) old('mit_rahmen', $kampagne->mit_rahmen ?? true)),
            html: @js(old('html', $kampagne->html ?? '')),
            text: @js(old('text', $kampagne->text ?? '')),
            urls: {
                reichweite: @js(route('module.newsletter.reichweite')),
                vorschau: @js(route('module.newsletter.vorschau')),
                testmail: @js(route('module.newsletter.testmail')),
                bild: @js(route('module.newsletter.bild')),
            },
            csrf: @js(csrf_token()),
            eigeneMail: @js(auth()->user()->email),
         })">

        <form method="POST" action="{{ $ziel }}" @submit="vorSpeichern">
            @csrf
            @unless ($neu) @method('PUT') @endunless

            <input type="hidden" name="bausteine" x-ref="json">
            <input type="hidden" name="modus" :value="modus">
            <input type="hidden" name="html" :value="html">
            <input type="hidden" name="text" :value="text">

            {{-- ── Kopf: Titel, Betreff, Empfänger ────────────────────────── --}}
            <div class="mb-6 grid gap-4 lg:grid-cols-3">
                <section class="rounded-xl border border-gray-200 bg-white p-5 lg:col-span-2">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="titel" class="block text-sm font-medium text-gray-700">
                                Titel <span class="font-normal text-gray-400">(nur intern)</span>
                            </label>
                            <input id="titel" name="titel" type="text" required maxlength="120"
                                   x-model="titel" @input="nachVorschau"
                                   placeholder="Elternbrief Juli"
                                   class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <p class="mt-1 text-xs text-gray-500">Damit findest du die Ausgabe später in der Liste wieder.</p>
                            @error('titel') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="betreff" class="block text-sm font-medium text-gray-700">
                                Betreff <span class="font-normal text-gray-400">(steht im Postfach)</span>
                            </label>
                            <input id="betreff" name="betreff" type="text" required maxlength="200"
                                   x-model="betreff" @input="nachVorschau"
                                   placeholder="Neues aus der Schule"
                                   class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error('betreff') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    {{-- Platzhalter gelten in BEIDEN Modi: im Baukasten getippt oder
                         im eigenen Code geschrieben – ersetzt wird beides. --}}
                    <div class="mt-4 border-t border-gray-100 pt-4">
                        <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">
                            Platzhalter (Klick zum Einfügen)
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @foreach (\Intranet\Modules\Newsletter\Support\Platzhalter::VERFUEGBAR as $name => $erklaerung)
                                @php($marke = '{'.'{ '.$name.' }'.'}')
                                <button type="button" @click="platzhalterEinfuegen(@js($marke))"
                                        title="{{ $erklaerung }}"
                                        class="rounded-lg border border-gray-300 bg-white px-2 py-1 font-mono text-xs text-gray-700 hover:border-indigo-400 hover:text-indigo-700">
                                    {{ $marke }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                </section>

                <section class="rounded-xl border border-gray-200 bg-white p-5">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Wer bekommt sie?</h2>

                    <div class="mt-3 max-h-64 space-y-2 overflow-y-auto pr-1">
                        <label class="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm hover:bg-gray-50">
                            <input type="checkbox" name="zielgruppen[]" value="alle" @change="reichweiteLaden()"
                                   @checked(in_array('alle', $gewaehlt, true))
                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="font-medium text-gray-800">Alle Benutzer</span>
                        </label>

                        @foreach ($rollen as $rolle)
                            <label class="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm hover:bg-gray-50">
                                <input type="checkbox" name="zielgruppen[]" value="{{ $rolle->role_id }}"
                                       @change="reichweiteLaden()"
                                       @checked(in_array($rolle->role_id, $gewaehlt, true))
                                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                <span class="text-gray-800">{{ $rolle->name }}</span>
                                <span class="text-xs text-gray-400">{{ $rolle->role_id }}</span>
                            </label>
                        @endforeach
                    </div>

                    {{-- Die Zahlen, bevor irgendetwas passiert. --}}
                    <div class="mt-4 rounded-lg bg-gray-50 p-3 text-sm" x-show="reichweite">
                        <p class="font-medium text-gray-800">
                            Erreicht <span x-text="reichweite?.erreichbar"></span>
                            von <span x-text="reichweite?.gesamt"></span> Personen
                        </p>
                        <ul class="mt-1 space-y-0.5 text-xs text-gray-500">
                            <li x-show="reichweite?.gesperrt > 0">
                                <span x-text="reichweite?.gesperrt"></span> gesperrt
                            </li>
                            <li x-show="reichweite?.unzustellbar > 0">
                                <span x-text="reichweite?.unzustellbar"></span> ohne echte Mailadresse
                            </li>
                            <li x-show="!reichweite?.gesperrt && !reichweite?.unzustellbar && reichweite?.gesamt > 0"
                                class="text-green-700">Alle erreichbar.</li>
                        </ul>
                    </div>
                </section>
            </div>

            {{-- ── Äußere Reiter: Baukasten / Eigener Code ────────────────── --}}
            <div class="border-b border-gray-200">
                <nav class="-mb-px flex gap-6">
                    @foreach (['bausteine' => 'Baukasten', 'code' => 'Eigener Code'] as $wert => $beschriftung)
                        <button type="button" @click="modusWechseln(@js($wert))"
                                class="border-b-2 px-1 py-3 text-sm font-medium"
                                :class="modus === @js($wert)
                                    ? 'border-indigo-600 text-indigo-700'
                                    : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'">
                            {{ $beschriftung }}
                        </button>
                    @endforeach
                </nav>
            </div>

            {{-- ══ Baukasten ═══════════════════════════════════════════════ --}}
            <div x-show="modus === 'bausteine'" class="pt-5">
                <p class="mb-4 text-sm text-gray-500">
                    Die Ausgabe besteht aus Bausteinen. Sie erscheinen in der Mail in der Reihenfolge,
                    in der sie hier stehen.
                </p>

                <div class="space-y-3">
                    <template x-for="(b, i) in bausteine" :key="b._id">
                        <div class="rounded-lg border border-gray-200 bg-gray-50/60 p-3">
                            <div class="mb-2 flex items-center justify-between">
                                <span class="text-xs font-semibold uppercase tracking-wide text-gray-500"
                                      x-text="bezeichnung(b.typ)"></span>
                                <div class="flex items-center gap-1">
                                    <button type="button" @click="hoch(i)" :disabled="i === 0"
                                            class="rounded p-1 text-gray-400 hover:bg-white hover:text-gray-700 disabled:opacity-30"
                                            title="Nach oben">▲</button>
                                    <button type="button" @click="runter(i)" :disabled="i === bausteine.length - 1"
                                            class="rounded p-1 text-gray-400 hover:bg-white hover:text-gray-700 disabled:opacity-30"
                                            title="Nach unten">▼</button>
                                    <button type="button" @click="entfernen(i)"
                                            class="rounded p-1 text-gray-400 hover:bg-white hover:text-red-600"
                                            title="Entfernen">
                                        <x-module-icon name="trash" class="text-base" />
                                    </button>
                                </div>
                            </div>

                            <template x-if="b.typ === 'ueberschrift'">
                                <div class="space-y-2">
                                    <input type="text" x-model="b.text" @input="nachVorschau" placeholder="Überschrift"
                                           class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <label class="flex items-center gap-2 text-sm text-gray-600">
                                        <input type="checkbox" x-model="b.gross" @change="nachVorschau"
                                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                        Große Überschrift
                                    </label>
                                </div>
                            </template>

                            <template x-if="b.typ === 'text'">
                                <div>
                                    <textarea x-model="b.text" @input="nachVorschau" rows="5" placeholder="Text …"
                                              class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                                    <p class="mt-1 text-xs text-gray-500">
                                        Eine Leerzeile trennt Absätze. Adressen, die mit http:// oder https:// beginnen,
                                        werden automatisch anklickbar.
                                    </p>
                                </div>
                            </template>

                            <template x-if="b.typ === 'bild'">
                                <div class="space-y-2">
                                    <template x-if="b.url">
                                        <img :src="b.url" alt="" class="max-h-40 rounded-lg border border-gray-200">
                                    </template>
                                    <input type="file" accept="image/*" @change="bildHochladen($event, b)"
                                           class="block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100">
                                    <input type="text" x-model="b.alt" @input="nachVorschau"
                                           placeholder="Bildbeschreibung (für Programme, die keine Bilder anzeigen)"
                                           class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <input type="url" x-model="b.link" @input="nachVorschau"
                                           placeholder="Bild verlinken auf … (optional)"
                                           class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                            </template>

                            <template x-if="b.typ === 'knopf'">
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <input type="text" x-model="b.text" @input="nachVorschau" placeholder="Beschriftung"
                                           class="rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    <input type="url" x-model="b.url" @input="nachVorschau" placeholder="https://…"
                                           class="rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                            </template>

                            <template x-if="b.typ === 'trenner'">
                                <hr class="border-gray-300">
                            </template>
                        </div>
                    </template>

                    <p x-show="bausteine.length === 0"
                       class="rounded-lg border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500">
                        Noch nichts drin. Füge unten den ersten Baustein hinzu.
                    </p>
                </div>

                <div class="mt-4 flex flex-wrap gap-2 border-t border-gray-100 pt-4">
                    @foreach (\Intranet\Modules\Newsletter\Support\Bausteine::TYPEN as $typ => $label)
                        <button type="button" @click="hinzufuegen(@js($typ))"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 hover:border-indigo-300 hover:bg-indigo-50">
                            <x-module-icon name="plus" class="text-sm" />
                            {{ $label }}
                        </button>
                    @endforeach
                </div>

                <div class="mt-6">
                    @include('newsletter::partials.vorschau')
                </div>
            </div>

            {{-- ══ Eigener Code ════════════════════════════════════════════ --}}
            <div x-show="modus === 'code'" class="pt-5">

                {{-- Rahmen ja/nein – nur hier relevant. --}}
                <div class="mb-4 rounded-xl border border-gray-200 bg-gray-50 p-4">
                    <div class="text-sm font-medium text-gray-700">Wie wird dein Code verschickt?</div>
                    <div class="mt-2 space-y-2">
                        <label class="flex items-start gap-2 text-sm">
                            <input type="radio" name="mit_rahmen" value="1" x-model="mitRahmen" @change="nachVorschau"
                                   class="mt-0.5 border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span>
                                <span class="font-medium text-gray-800">In die Vorlage einsetzen</span>
                                <span class="block text-xs text-gray-500">
                                    Dein HTML kommt als Inhalt in den Newsletter-Rahmen – mit Kopf, Logo,
                                    Anrede und Fuß. Wie beim Baukasten, nur der Inhalt ist selbst geschrieben.
                                </span>
                            </span>
                        </label>
                        <label class="flex items-start gap-2 text-sm">
                            <input type="radio" name="mit_rahmen" value="0" x-model="mitRahmen" @change="nachVorschau"
                                   class="mt-0.5 border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span>
                                <span class="font-medium text-gray-800">Komplett eigener Code</span>
                                <span class="block text-xs text-gray-500">
                                    Dein HTML <strong>ist</strong> die ganze Mail – kein Rahmen, keine Anrede.
                                    Du lieferst alles selbst, inklusive <code>&lt;html&gt;</code> und Fußzeile.
                                </span>
                            </span>
                        </label>
                    </div>

                    <p x-show="mitRahmen === '0'" x-cloak
                       class="mt-3 flex items-start gap-1.5 border-t border-gray-200 pt-3 text-xs text-amber-700">
                        <i class='bx bx-error-circle mt-0.5'></i>
                        <span>
                            Ohne Rahmen fehlen Abmeldehinweis und einheitliches Aussehen. Schick dir vor der
                            Freigabe unbedingt eine Testmail und sieh sie dir in Outlook an.
                        </span>
                    </p>
                </div>

                <div class="mb-4 flex justify-end">
                    <button type="button" @click="ausBausteinen"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 hover:border-indigo-300 hover:bg-indigo-50">
                        <x-module-icon name="import" class="text-sm" />
                        Aus Baukasten übernehmen
                    </button>
                </div>

                {{-- Innere Reiter – wie in der Vorlagenverwaltung --}}
                <div class="border-b border-gray-200">
                    <nav class="-mb-px flex gap-6">
                        @foreach ([
                            'html' => 'Formatierte Fassung',
                            'text' => 'Reiner Text',
                            'vorschau' => 'Vorschau',
                        ] as $wert => $beschriftung)
                            <button type="button" @click="codeReiter = @js($wert)"
                                    class="border-b-2 px-1 py-2.5 text-sm font-medium"
                                    :class="codeReiter === @js($wert)
                                        ? 'border-indigo-600 text-indigo-700'
                                        : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'">
                                {{ $beschriftung }}
                            </button>
                        @endforeach
                    </nav>
                </div>

                {{-- Formatierte Fassung --}}
                <div x-show="codeReiter === 'html'" class="pt-4">
                    <div class="mb-1 flex items-center justify-between">
                        <label class="text-sm font-medium text-gray-700">Formatierte Fassung (HTML)</label>
                        <button type="button" @click="htmlModus = ! htmlModus"
                                class="text-xs text-indigo-600 hover:underline"
                                x-text="htmlModus ? 'zurück zur Ansicht' : 'HTML-Quelltext'"></button>
                    </div>

                    <div x-show="! htmlModus" class="flex flex-wrap gap-1 rounded-t-lg border border-b-0 border-gray-300 bg-gray-50 p-1">
                        <button type="button" @click="format('bold')" class="rounded px-2 py-1 text-sm font-bold hover:bg-gray-200">B</button>
                        <button type="button" @click="format('italic')" class="rounded px-2 py-1 text-sm italic hover:bg-gray-200">I</button>
                        <button type="button" @click="format('insertUnorderedList')" class="rounded px-2 py-1 text-sm hover:bg-gray-200">• Liste</button>
                        <button type="button" @click="linkSetzen" class="rounded px-2 py-1 text-sm hover:bg-gray-200">🔗 Link</button>
                    </div>

                    <div x-show="! htmlModus" x-ref="wysiwyg" contenteditable="true" @input="ausWysiwyg"
                         class="newsletter-wysiwyg block max-h-[32rem] min-h-[20rem] w-full overflow-auto break-words rounded-b-lg border border-gray-300 bg-white p-3 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500"></div>

                    <textarea x-show="htmlModus" x-model="html" @input="nachVorschau" spellcheck="false"
                              class="block h-[32rem] w-full rounded-lg border-gray-300 font-mono text-xs"></textarea>
                </div>

                {{-- Reiner Text --}}
                <div x-show="codeReiter === 'text'" class="pt-4">
                    <label class="mb-1 block text-sm font-medium text-gray-700">Reiner Text (ohne Formatierung)</label>
                    <p class="mb-2 text-xs text-gray-500">
                        Geht als zweite Spur mit und wird angezeigt, wenn ein Mailprogramm kein HTML
                        darstellt. Bleibt das Feld leer, bekommen diese Empfänger eine leere Mail.
                    </p>
                    <textarea x-model="text" @input="nachVorschau" spellcheck="false"
                              class="block h-[32rem] w-full rounded-lg border-gray-300 font-mono text-xs"></textarea>
                </div>

                {{-- Vorschau --}}
                <div x-show="codeReiter === 'vorschau'" class="pt-4">
                    @include('newsletter::partials.vorschau')
                </div>
            </div>

            <div class="mt-6 flex items-center gap-3 border-t border-gray-200 pt-4">
                <button type="submit"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    <i class='bx bx-save'></i> Speichern
                </button>
                <span class="text-xs text-gray-500">Speichern verschickt noch nichts.</span>
            </div>
        </form>
    </div>

    {{-- Echtes CSS statt Tailwind: Die Regeln greifen auf Elemente, die erst
         der Benutzer in das Feld schreibt. --}}
    <style>
        .newsletter-wysiwyg img,
        .newsletter-wysiwyg table { max-width: 100%; }
        .newsletter-wysiwyg img { height: auto; }
    </style>

    @push('scripts')
    <script>
        function newsletterEditor(config) {
            return {
                // Inhalt
                modus: config.modus,
                // '1'/'0' als String, weil an Radio-Buttons gebunden. Nur im
                // Code-Modus von Belang; der Baukasten braucht den Rahmen immer.
                mitRahmen: config.mitRahmen,
                bausteine: [],
                html: config.html,
                text: config.text,
                titel: @js(old('titel', $kampagne->titel ?? '')),
                betreff: @js(old('betreff', $kampagne->betreff ?? '')),

                // Oberfläche
                codeReiter: 'html',
                htmlModus: false,
                reichweite: null,
                vorschauBetreff: '',

                // Testmail
                testEmail: config.eigeneMail,
                testLaeuft: false,
                testOk: false,
                testMeldung: '',

                _zaehler: 0,
                _timer: null,
                _lauf: 0,
                // Elementbezüge EINMAL merken: Alpines $refs sind nur im
                // Auswertungs-Kontext verfügbar – in einem setTimeout- oder
                // await-Callback sind sie undefined, und der Zugriff wirft dort
                // still einen Fehler (genau daran hing die Vorschau im
                // Vorlagen-Editor mal fest).
                _wysiwyg: null,
                _vorschau: null,
                _json: null,

                init() {
                    // Jeder Baustein bekommt eine Kennung, damit Alpine beim
                    // Umsortieren die Felder mitnimmt statt sie neu zu zeichnen
                    // (sonst springt der Cursor beim Tippen).
                    this.bausteine = (config.bausteine || []).map(b => ({ ...b, _id: ++this._zaehler }));

                    this._wysiwyg = this.$refs.wysiwyg;
                    this._vorschau = this.$refs.vorschau;
                    this._json = this.$refs.json;

                    if (this._wysiwyg) {
                        this._wysiwyg.innerHTML = this.html;
                        // Beim Zurückschalten aus dem Quelltext neu befüllen.
                        this.$watch('htmlModus', (an) => {
                            if (! an) this._wysiwyg.innerHTML = this.html;
                        });
                    }

                    this.reichweiteLaden();
                    this.nachVorschau();
                },

                // ── Modus ───────────────────────────────────────────────────
                modusWechseln(neu) {
                    this.modus = neu;
                    this.nachVorschau();
                },

                /**
                 * Den Baukasten-Inhalt als Startpunkt in den Code-Modus holen.
                 * Überschreibt vorhandenen Code nur nach Rückfrage – sonst wäre
                 * ein Fehlklick teuer.
                 */
                async ausBausteinen() {
                    if (this.html.trim() && ! confirm('Der vorhandene eigene Code wird ersetzt. Fortfahren?')) return;

                    const antwort = await this.holen(config.urls.vorschau, {
                        titel: this.titel,
                        betreff: this.betreff,
                        modus: 'bausteine',
                        bausteine: JSON.stringify(this.bausteine),
                    });

                    if (! antwort) return;

                    this.html = antwort.inhalt_html;
                    this.text = antwort.inhalt_text;
                    if (this._wysiwyg) this._wysiwyg.innerHTML = this.html;
                    this.nachVorschau();
                },

                // ── Bausteine ───────────────────────────────────────────────
                bezeichnung(typ) {
                    return @js(\Intranet\Modules\Newsletter\Support\Bausteine::TYPEN)[typ] ?? typ;
                },

                hinzufuegen(typ) {
                    const vorlage = { typ, _id: ++this._zaehler };

                    if (typ === 'ueberschrift') Object.assign(vorlage, { text: '', gross: false });
                    if (typ === 'text') Object.assign(vorlage, { text: '' });
                    if (typ === 'bild') Object.assign(vorlage, { url: '', alt: '', link: '' });
                    if (typ === 'knopf') Object.assign(vorlage, { text: '', url: '' });

                    this.bausteine.push(vorlage);
                    this.nachVorschau();
                },

                entfernen(i) { this.bausteine.splice(i, 1); this.nachVorschau(); },

                hoch(i) {
                    if (i === 0) return;
                    this.bausteine.splice(i - 1, 0, this.bausteine.splice(i, 1)[0]);
                    this.nachVorschau();
                },

                runter(i) {
                    if (i >= this.bausteine.length - 1) return;
                    this.bausteine.splice(i + 1, 0, this.bausteine.splice(i, 1)[0]);
                    this.nachVorschau();
                },

                async bildHochladen(event, baustein) {
                    const datei = event.target.files[0];
                    if (! datei) return;

                    const daten = new FormData();
                    daten.append('bild', datei);

                    const antwort = await fetch(config.urls.bild, {
                        method: 'POST',
                        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': config.csrf },
                        body: daten,
                    });

                    if (! antwort.ok) {
                        this.testOk = false;
                        this.testMeldung = 'Das Bild konnte nicht hochgeladen werden (max. 4 MB, nur Bilddateien).';
                        return;
                    }

                    baustein.url = (await antwort.json()).url;
                    this.nachVorschau();
                },

                // ── Eigener Code ────────────────────────────────────────────
                format(befehl) {
                    document.execCommand(befehl, false, null);
                    this.ausWysiwyg();
                },

                linkSetzen() {
                    const url = prompt('Link-Adresse:');
                    if (url) { document.execCommand('createLink', false, url); this.ausWysiwyg(); }
                },

                ausWysiwyg() {
                    this.html = this._wysiwyg.innerHTML;
                    this.nachVorschau();
                },

                platzhalterEinfuegen(marke) {
                    // An die Cursorposition – aber nur, wenn der Cursor auch
                    // wirklich im Formatier-Feld steht. Sonst in die
                    // Zwischenablage, weil insertText sonst ins Leere liefe.
                    if (this._wysiwyg && this.modus === 'code' && ! this.htmlModus
                        && document.activeElement === this._wysiwyg) {
                        document.execCommand('insertText', false, marke);
                        this.ausWysiwyg();
                        return;
                    }

                    navigator.clipboard?.writeText(marke);
                },

                // ── Empfänger ───────────────────────────────────────────────
                gewaehlteZielgruppen() {
                    return [...document.querySelectorAll('input[name="zielgruppen[]"]:checked')].map(e => e.value);
                },

                async reichweiteLaden() {
                    this.reichweite = await this.holen(config.urls.reichweite, {
                        zielgruppen: this.gewaehlteZielgruppen(),
                    });
                },

                // ── Vorschau ────────────────────────────────────────────────
                formularwerte() {
                    return {
                        titel: this.titel,
                        betreff: this.betreff,
                        modus: this.modus,
                        mit_rahmen: this.mitRahmen,
                        bausteine: JSON.stringify(this.bausteine),
                        html: this.html,
                        text: this.text,
                    };
                },

                nachVorschau() {
                    clearTimeout(this._timer);
                    this._timer = setTimeout(() => this.vorschauLaden(), 350);
                },

                async vorschauLaden() {
                    // Laufende Nummer je Anfrage: Beim Tippen sind mehrere
                    // unterwegs. Ohne diese Prüfung kann eine ÄLTERE Antwort
                    // zuletzt eintreffen und die neuere überschreiben – die
                    // Vorschau zeigt dann veraltete Werte.
                    const lauf = ++this._lauf;
                    const fertig = await this.holen(config.urls.vorschau, this.formularwerte());

                    if (! fertig || lauf !== this._lauf) return;

                    this.vorschauBetreff = fertig.betreff;
                    // Zwei Vorschau-Rahmen im Dokument (Baukasten und Code-Reiter),
                    // aber immer nur einer sichtbar – beide befüllen.
                    document.querySelectorAll('iframe[title="Vorschau"]')
                        .forEach(rahmen => { rahmen.srcdoc = fertig.html; });
                },

                async testSenden() {
                    if (! this.testEmail) { this.testOk = false; this.testMeldung = 'Bitte eine Adresse eingeben.'; return; }

                    this.testLaeuft = true;
                    this.testMeldung = '';

                    const antwort = await this.holen(config.urls.testmail, {
                        ...this.formularwerte(),
                        an: this.testEmail,
                    });

                    this.testOk = !! antwort?.ok;
                    this.testMeldung = antwort?.meldung ?? 'Senden fehlgeschlagen.';
                    this.testLaeuft = false;
                },

                async holen(url, daten) {
                    try {
                        const antwort = await fetch(url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': config.csrf,
                            },
                            body: JSON.stringify(daten),
                        });

                        return await antwort.json();
                    } catch (e) {
                        return null;
                    }
                },

                vorSpeichern() {
                    // Die versteckten Felder auf den aktuellen Stand bringen.
                    if (this._wysiwyg && ! this.htmlModus) this.html = this._wysiwyg.innerHTML;
                    this._json.value = JSON.stringify(this.bausteine);
                },
            };
        }
    </script>
    @endpush
</x-app-layout>
