@php
    $neu = ! $kampagne->exists;
    $ziel = $neu ? route('module.newsletter.store') : route('module.newsletter.update', $kampagne);
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
            urls: {
                reichweite: @js(route('module.newsletter.reichweite')),
                vorschau: @js(route('module.newsletter.vorschau')),
                testmail: @js(route('module.newsletter.testmail')),
                bild: @js(route('module.newsletter.bild')),
            },
            csrf: @js(csrf_token()),
            eigeneMail: @js(auth()->user()->email),
         })">

        <form method="POST" action="{{ $ziel }}" @submit="feldJson.value = JSON.stringify(bausteine)">
            @csrf
            @unless ($neu) @method('PUT') @endunless

            <input type="hidden" name="bausteine" x-ref="json">

            <div class="grid gap-6 xl:grid-cols-3">

                {{-- ── Linke Spalte: Inhalt ─────────────────────────────── --}}
                <div class="space-y-6 xl:col-span-2">

                    <section class="rounded-xl border border-gray-200 bg-white p-5">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="titel" class="block text-sm font-medium text-gray-700">
                                    Titel <span class="font-normal text-gray-400">(nur intern)</span>
                                </label>
                                <input id="titel" name="titel" type="text" required maxlength="120"
                                       value="{{ old('titel', $kampagne->titel) }}"
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
                                       value="{{ old('betreff', $kampagne->betreff) }}"
                                       placeholder="Neues aus der Schule"
                                       class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                @error('betreff') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </section>

                    <section class="rounded-xl border border-gray-200 bg-white p-5">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Inhalt</h2>
                        <p class="mt-1 text-sm text-gray-500">
                            Die Ausgabe besteht aus Bausteinen. Sie erscheinen in der Mail in der Reihenfolge,
                            in der sie hier stehen.
                        </p>

                        <div class="mt-4 space-y-3">
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

                                    {{-- Überschrift --}}
                                    <template x-if="b.typ === 'ueberschrift'">
                                        <div class="space-y-2">
                                            <input type="text" x-model="b.text" placeholder="Überschrift"
                                                   class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            <label class="flex items-center gap-2 text-sm text-gray-600">
                                                <input type="checkbox" x-model="b.gross"
                                                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                                Große Überschrift
                                            </label>
                                        </div>
                                    </template>

                                    {{-- Textabsatz --}}
                                    <template x-if="b.typ === 'text'">
                                        <div>
                                            <textarea x-model="b.text" rows="5" placeholder="Text …"
                                                      class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                                            <p class="mt-1 text-xs text-gray-500">
                                                Eine Leerzeile trennt Absätze. Adressen, die mit http:// oder https:// beginnen,
                                                werden automatisch anklickbar.
                                            </p>
                                        </div>
                                    </template>

                                    {{-- Bild --}}
                                    <template x-if="b.typ === 'bild'">
                                        <div class="space-y-2">
                                            <template x-if="b.url">
                                                <img :src="b.url" alt="" class="max-h-40 rounded-lg border border-gray-200">
                                            </template>
                                            <input type="file" accept="image/*" @change="bildHochladen($event, b)"
                                                   class="block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100">
                                            <input type="text" x-model="b.alt" placeholder="Bildbeschreibung (für Programme, die keine Bilder anzeigen)"
                                                   class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            <input type="url" x-model="b.link" placeholder="Bild verlinken auf … (optional)"
                                                   class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        </div>
                                    </template>

                                    {{-- Knopf --}}
                                    <template x-if="b.typ === 'knopf'">
                                        <div class="grid gap-2 sm:grid-cols-2">
                                            <input type="text" x-model="b.text" placeholder="Beschriftung"
                                                   class="rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            <input type="url" x-model="b.url" placeholder="https://…"
                                                   class="rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        </div>
                                    </template>

                                    {{-- Trennlinie --}}
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
                    </section>
                </div>

                {{-- ── Rechte Spalte: Empfänger, Vorschau, Speichern ─────── --}}
                <div class="space-y-6">

                    <section class="rounded-xl border border-gray-200 bg-white p-5">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Wer bekommt sie?</h2>

                        <div class="mt-3 space-y-2">
                            <label class="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm hover:bg-gray-50">
                                <input type="checkbox" name="zielgruppen[]" value="alle"
                                       @change="reichweiteLaden()"
                                       @checked(in_array('alle', old('zielgruppen', $kampagne->zielgruppen ?? []), true))
                                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                <span class="font-medium text-gray-800">Alle Benutzer</span>
                            </label>

                            @foreach ($rollen as $rolle)
                                <label class="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm hover:bg-gray-50">
                                    <input type="checkbox" name="zielgruppen[]" value="{{ $rolle->role_id }}"
                                           @change="reichweiteLaden()"
                                           @checked(in_array($rolle->role_id, old('zielgruppen', $kampagne->zielgruppen ?? []), true))
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

                    <section class="rounded-xl border border-gray-200 bg-white p-5">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Vorschau &amp; Test</h2>

                        <div class="mt-3 flex flex-wrap gap-2">
                            <button type="button" @click="vorschauLaden()"
                                    class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">
                                Vorschau anzeigen
                            </button>
                            <button type="button" @click="testmailSenden()"
                                    class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50">
                                Testmail an mich
                            </button>
                        </div>

                        <p x-show="meldung" x-text="meldung"
                           :class="meldungOk ? 'text-green-700' : 'text-red-600'"
                           class="mt-2 text-xs"></p>

                        <iframe x-show="vorschauHtml" x-ref="vorschau"
                                class="mt-3 h-96 w-full rounded-lg border border-gray-200"
                                title="Vorschau"></iframe>
                    </section>

                    <div class="flex items-center gap-2">
                        <button type="submit"
                                class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                            Speichern
                        </button>
                        <span class="text-xs text-gray-500">Speichern verschickt noch nichts.</span>
                    </div>
                </div>
            </div>
        </form>
    </div>

    @push('scripts')
    <script>
        function newsletterEditor(config) {
            return {
                bausteine: [],
                reichweite: null,
                vorschauHtml: '',
                meldung: '',
                meldungOk: true,
                feldJson: null,
                _zaehler: 0,

                init() {
                    // Jeder Baustein bekommt eine Kennung, damit Alpine beim
                    // Umsortieren die Felder mitnimmt statt sie neu zu zeichnen
                    // (sonst springt der Cursor beim Tippen).
                    this.bausteine = (config.bausteine || []).map(b => ({ ...b, _id: this.naechsteId() }));
                    this.feldJson = this.$refs.json;
                    this.reichweiteLaden();
                },

                naechsteId() {
                    return ++this._zaehler;
                },

                bezeichnung(typ) {
                    return @js(\Intranet\Modules\Newsletter\Support\Bausteine::TYPEN)[typ] ?? typ;
                },

                hinzufuegen(typ) {
                    const vorlage = { typ, _id: this.naechsteId() };

                    if (typ === 'ueberschrift') Object.assign(vorlage, { text: '', gross: false });
                    if (typ === 'text') Object.assign(vorlage, { text: '' });
                    if (typ === 'bild') Object.assign(vorlage, { url: '', alt: '', link: '' });
                    if (typ === 'knopf') Object.assign(vorlage, { text: '', url: '' });

                    this.bausteine.push(vorlage);
                },

                entfernen(i) { this.bausteine.splice(i, 1); },

                hoch(i) {
                    if (i === 0) return;
                    this.bausteine.splice(i - 1, 0, this.bausteine.splice(i, 1)[0]);
                },

                runter(i) {
                    if (i >= this.bausteine.length - 1) return;
                    this.bausteine.splice(i + 1, 0, this.bausteine.splice(i, 1)[0]);
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
                        this.zeigen('Das Bild konnte nicht hochgeladen werden (max. 4 MB, nur Bilddateien).', false);
                        return;
                    }

                    baustein.url = (await antwort.json()).url;
                    this.zeigen('Bild hochgeladen.', true);
                },

                gewaehlteZielgruppen() {
                    return [...document.querySelectorAll('input[name="zielgruppen[]"]:checked')].map(e => e.value);
                },

                async reichweiteLaden() {
                    this.reichweite = await this.holen(config.urls.reichweite, {
                        zielgruppen: this.gewaehlteZielgruppen(),
                    });
                },

                formularwerte() {
                    return {
                        titel: document.getElementById('titel').value,
                        betreff: document.getElementById('betreff').value,
                        bausteine: JSON.stringify(this.bausteine),
                    };
                },

                async vorschauLaden() {
                    const fertig = await this.holen(config.urls.vorschau, this.formularwerte());
                    if (! fertig) return;

                    this.vorschauHtml = fertig.html;
                    this.$refs.vorschau.srcdoc = fertig.html;
                },

                async testmailSenden() {
                    const antwort = await this.holen(config.urls.testmail, {
                        ...this.formularwerte(),
                        an: config.eigeneMail,
                    });

                    this.zeigen(
                        antwort?.meldung ?? 'Die Testmail konnte nicht verschickt werden.',
                        !! antwort?.ok,
                    );
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
                        this.zeigen('Verbindung zum Server fehlgeschlagen.', false);
                        return null;
                    }
                },

                zeigen(text, ok) {
                    this.meldung = text;
                    this.meldungOk = ok;
                },
            };
        }
    </script>
    @endpush
</x-app-layout>
