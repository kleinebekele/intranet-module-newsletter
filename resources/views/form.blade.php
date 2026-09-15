@php
    $neu = ! $kampagne->exists;
    $ziel = $neu ? route('module.newsletter.store') : route('module.newsletter.update', $kampagne);
    $gewaehlt = old('zielgruppen', $kampagne->zielgruppen ?? []);
    // Rahmen-Dropdown: '' = allgemeiner Rahmen, 'keine' = ohne Rahmen, Zahl = eigene Vorlage.
    $rahmenWert = old('rahmen', $kampagne->mit_rahmen === false ? 'keine' : (string) ($kampagne->vorlage_id ?? ''));
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

    {{-- konten: Vorgaben je SMTP-Absender (Dropdown) – beim Wechsel werden Name und
         Antwort-an damit ersetzt; „Standard" setzt die Werte der Instanz.
         Innerhalb des x-data-Attributs keine Kommentare mit Anführungszeichen:
         ein gerades " beendet das Attribut, und Alpine bindet dann titel/betreff
         an die globalen Input-Elemente ("[object HTMLInputElement]"). --}}
    <div x-data="newsletterEditor({
            bausteine: @js($kampagne->bausteine ?? []),
            urls: {
                reichweite: @js(route('module.newsletter.reichweite')),
                vorschau: @js(route('module.newsletter.vorschau')),
                testmail: @js(route('module.newsletter.testmail')),
                bild: @js(route('module.newsletter.bild')),
                gruppen: @js(route('module.newsletter.gruppen.store')),
                benutzer: @js(route('module.newsletter.gruppen.benutzer')),
            },
            gruppen: @js($gruppen),
            gewaehlteGruppen: @js(array_values(array_filter((array) $gewaehlt, fn ($z) => is_string($z) && str_starts_with($z, 'gruppe:')))),
            rollen: @js($rollen->map(fn ($r) => ['id' => $r->role_id, 'name' => $r->name])->values()),
            csrf: @js(csrf_token()),
            eigeneMail: @js(auth()->user()->email),
            konten: @js($konten->map(fn ($k) => ['id' => (string) $k->id, 'name' => (string) ($k->absender_name ?? ''), 'antwort' => (string) ($k->antwort_an ?? '')])->values()),
            standardName: @js((string) config('mail.from.name')),
         })">

        <form method="POST" action="{{ $ziel }}" @submit="vorSpeichern">
            @csrf
            @unless ($neu) @method('PUT') @endunless

            <input type="hidden" name="bausteine" x-ref="json">

            {{-- ── Kopf: Titel, Betreff, Rahmen, Absender, Empfänger ─────────── --}}
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
                                   x-model="betreff" @input="nachVorschau" @focusin="feldMerken($event)"
                                   placeholder="Neues aus der Schule"
                                   class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <p class="mt-1 text-xs text-gray-500">Platzhalter wie <code>{{ '{'.'{ ausgabe }'.'}' }}</code> gehen auch hier (siehe unten bei den Bausteinen).</p>
                            @error('betreff') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    {{-- Rahmen je Ausgabe: eigene Vorlage der Redaktion (Menüpunkt
                         „Mailvorlagen"), der allgemeine Rahmen des Intranets – oder gar
                         keiner (dann sind die Bausteine die ganze Mail). --}}
                    <div class="mt-4 border-t border-gray-100 pt-4">
                        <label for="rahmen" class="block text-sm font-medium text-gray-700">
                            Mailvorlage <span class="font-normal text-gray-400">(Rahmen um die Ausgabe)</span>
                        </label>
                        <select id="rahmen" name="rahmen" x-model="rahmen" @change="nachVorschau"
                                class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 sm:max-w-md">
                            <option value="">Allgemeiner Rahmen des Intranets</option>
                            @foreach ($vorlagen as $vorlage)
                                <option value="{{ $vorlage->id }}">{{ $vorlage->name }}</option>
                            @endforeach
                            <option value="keine">Keine – nur eigener Code</option>
                        </select>
                        <p class="mt-1 text-xs text-gray-500">
                            Kopf, Fuß und Anrede rund um den Inhalt. Eigene Rahmen pflegst du unter
                            <a href="{{ route('module.newsletter.vorlagen.index') }}" class="text-indigo-600 hover:underline">Mailvorlagen</a>.
                        </p>
                        <p x-show="rahmen === 'keine'" x-cloak
                           class="mt-2 flex items-start gap-1.5 text-xs text-amber-700">
                            <i class='bx bx-error-circle mt-0.5'></i>
                            <span>
                                Ohne Rahmen ist dein HTML-Baustein die <strong>ganze Mail</strong> – kein Kopf, keine Anrede,
                                kein Abmeldehinweis. Du lieferst alles selbst, inklusive <code>&lt;html&gt;</code>.
                                Schick dir vor der Freigabe eine Testmail und sieh sie dir in Outlook an.
                            </span>
                        </p>
                        @error('rahmen') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </div>

                    {{-- Absender je Ausgabe: wahlweise ein SMTP-Absender aus der Verwaltung
                         (eigenes Postfach, eigene Adresse) oder der Standard der Instanz.
                         Name und Antwort-an sind immer editierbar; der Wechsel im Dropdown
                         setzt sie auf die Vorgaben des gewählten Kontos zurück. --}}
                    <div class="mt-4 grid gap-4 border-t border-gray-100 pt-4 sm:grid-cols-3">
                        <div>
                            <label for="mail_konto_id" class="block text-sm font-medium text-gray-700">
                                Absender <span class="font-normal text-gray-400">(Postfach)</span>
                            </label>
                            <select id="mail_konto_id" name="mail_konto_id" x-model="kontoId" @change="kontoGewechselt"
                                    class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                <option value="">Standard ({{ config('mail.from.address') }})</option>
                                @foreach ($konten as $konto)
                                    <option value="{{ $konto->id }}">{{ $konto->bezeichnung }} ({{ $konto->absender_mail }})</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-gray-500">
                                @if ($konten->isEmpty())
                                    Weitere Postfächer legt die Verwaltung unter Maillog → SMTP-Absender an.
                                @else
                                    Über welches Postfach die Ausgabe rausgeht. Der Wechsel setzt Name und Antwort-an auf dessen Vorgaben.
                                @endif
                            </p>
                            @error('mail_konto_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="absender_name" class="block text-sm font-medium text-gray-700">
                                Absendername <span class="font-normal text-gray-400">(wird angezeigt)</span>
                            </label>
                            <input id="absender_name" name="absender_name" type="text" maxlength="120"
                                   x-model="absenderName"
                                   placeholder="{{ config('mail.from.name') }}"
                                   class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <p class="mt-1 text-xs text-gray-500">Leer = Vorgabe des gewählten Postfachs bzw. „{{ config('mail.from.name') }}".</p>
                            @error('absender_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="antwort_an" class="block text-sm font-medium text-gray-700">
                                Antworten an <span class="font-normal text-gray-400">(Mailadresse)</span>
                            </label>
                            <input id="antwort_an" name="antwort_an" type="email" maxlength="191"
                                   x-model="antwortAn"
                                   placeholder="redaktion@example.org"
                                   class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <p class="mt-1 text-xs text-gray-500">Wer auf die Mail antwortet, schreibt an diese Adresse. Leer = Vorgabe des Postfachs bzw. Standard der Instanz.</p>
                            @error('antwort_an') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </section>

                <section class="rounded-xl border border-gray-200 bg-white p-5">
                    <div class="flex items-center justify-between">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Wer bekommt sie?</h2>
                        <button type="button" @click="gruppeNeu()" class="text-xs text-indigo-600 hover:underline">
                            + Eigene Gruppe
                        </button>
                    </div>

                    <div class="mt-3 max-h-64 space-y-2 overflow-y-auto pr-1">
                        {{-- Manuelle Gruppen: immer ganz oben, eigene bearbeitbar. --}}
                        <template x-for="g in gruppen" :key="g.id">
                            <label class="flex items-center gap-2 rounded-lg border border-indigo-200 bg-indigo-50/40 px-3 py-2 text-sm hover:bg-indigo-50">
                                <input type="checkbox" name="zielgruppen[]" :value="g.kennung" x-model="gewaehlteGruppen"
                                       @change="reichweiteLaden()"
                                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                <span class="flex-1 text-gray-800" x-text="g.name"></span>
                                <span class="rounded-full bg-indigo-100 px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide text-indigo-700">manuell</span>
                                <button type="button" @click.prevent="gruppeBearbeiten(g)" class="text-xs text-indigo-600 hover:underline">Bearbeiten</button>
                            </label>
                        </template>
                        @foreach ($fremdeGruppen as $fg)
                            <label class="flex items-center gap-2 rounded-lg border border-indigo-200 bg-indigo-50/40 px-3 py-2 text-sm hover:bg-indigo-50">
                                <input type="checkbox" name="zielgruppen[]" value="{{ $fg['kennung'] }}" @change="reichweiteLaden()"
                                       @checked(in_array($fg['kennung'], $gewaehlt, true))
                                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                <span class="flex-1 text-gray-800">{{ $fg['name'] }}</span>
                                <span class="rounded-full bg-indigo-100 px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide text-indigo-700">manuell</span>
                                <span class="text-xs text-gray-400" title="Gruppe von {{ $fg['besitzer'] }} – nur der Ersteller kann sie ändern">von {{ $fg['besitzer'] }}</span>
                            </label>
                        @endforeach

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

            {{-- ══ Baukasten ═══════════════════════════════════════════════ --}}
            <div class="pt-2">
                <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                    <p class="text-sm text-gray-500">
                        Die Ausgabe besteht aus Bausteinen. Sie erscheinen in der Mail in der Reihenfolge,
                        in der sie hier stehen.
                    </p>

                    {{-- Platzhalter direkt bei den Feldern, in denen sie landen: Klick fügt
                         sie an der Cursorposition des zuletzt benutzten Feldes ein (Betreff,
                         Text- oder HTML-Baustein). --}}
                    <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
                        <div class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-500">
                            Platzhalter <span class="font-normal normal-case tracking-normal text-gray-400">– Klick fügt sie ins zuletzt benutzte Feld ein</span>
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
                        <p x-show="platzhalterHinweis" x-cloak x-text="platzhalterHinweis" class="mt-1 text-xs text-amber-700"></p>
                    </div>
                </div>

                <div class="space-y-3" @focusin="feldMerken($event)">
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

                            {{-- Eigener Code – roh, nicht maskiert. Ersetzt den früheren
                                 Reiter „Eigener Code". --}}
                            <template x-if="b.typ === 'html'">
                                <div>
                                    <textarea x-model="b.html" @input="nachVorschau" rows="14" spellcheck="false"
                                              placeholder="<p style=&quot;…&quot;>Eigenes HTML …</p>"
                                              class="w-full rounded-lg border-gray-300 font-mono text-xs focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                                    <p class="mt-1 text-xs text-gray-500">
                                        Wird unverändert in die Mail übernommen. Tabellenbasiertes HTML mit Inline-Styles
                                        sieht in Outlook, Gmail &amp; Co. verlässlich gleich aus. Mit der Mailvorlage
                                        „Keine – nur eigener Code" ist dieser Baustein die ganze Mail.
                                    </p>
                                </div>
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

            <div class="mt-6 flex items-center gap-3 border-t border-gray-200 pt-4">
                <button type="submit"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    <i class='bx bx-save'></i> Speichern
                </button>
                <span class="text-xs text-gray-500">Speichern verschickt noch nichts.</span>
            </div>
        </form>

        {{-- ── Modal: eigene Gruppe anlegen/bearbeiten ─────────────────────
             Außerhalb des Formulars, damit die Felder nicht mitgeschickt werden. --}}
        <div x-show="modal.offen" x-cloak
             @keydown.escape.window="modal.offen && modalSchliessen()"
             class="fixed inset-0 z-[60] flex items-center justify-center bg-gray-900/50 p-4"
             role="dialog" aria-modal="true" aria-label="Eigene Gruppe">
            <div @click.outside="modalSchliessen()"
                 class="flex max-h-full w-full max-w-3xl flex-col rounded-xl bg-white shadow-xl">
                <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4">
                    <h3 class="font-semibold text-gray-800" x-text="modal.id ? 'Gruppe bearbeiten' : 'Eigene Gruppe anlegen'"></h3>
                    <button type="button" @click="modalSchliessen()" class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700" title="Schließen">✕</button>
                </div>

                <div class="overflow-y-auto px-5 py-4">
                    <label class="block text-sm font-medium text-gray-700">Name</label>
                    <input type="text" x-model="modal.name" maxlength="120" placeholder="z. B. Elternrat + Hausmeister"
                           class="mt-1 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <p class="mt-1 text-xs text-gray-500">Nur du siehst diese Gruppe. Ausschlüsse gewinnen gegen Einschlüsse.</p>

                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <template x-for="seite in ['ein', 'aus']" :key="seite">
                            <section class="rounded-lg border p-3" :class="seite === 'ein' ? 'border-emerald-200 bg-emerald-50/30' : 'border-red-200 bg-red-50/30'">
                                <h4 class="text-xs font-semibold uppercase tracking-wide" :class="seite === 'ein' ? 'text-emerald-700' : 'text-red-700'"
                                    x-text="seite === 'ein' ? 'Einschließen' : 'Ausschließen'"></h4>

                                <div class="mt-2 text-xs font-medium text-gray-600">Gruppen (Rollen)</div>
                                <div class="mt-1 max-h-36 space-y-1 overflow-y-auto pr-1">
                                    <template x-for="r in rollen" :key="seite + r.id">
                                        <label class="flex items-center gap-2 text-sm">
                                            <input type="checkbox" :value="r.id" x-model="modal['rollen_' + seite]"
                                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                            <span x-text="r.name"></span>
                                        </label>
                                    </template>
                                </div>

                                <div class="mt-3 text-xs font-medium text-gray-600">Einzelne Kontakte</div>
                                <div class="mt-1 flex flex-wrap gap-1">
                                    <template x-for="k in modal['user_' + seite]" :key="seite + k.id">
                                        <span class="inline-flex items-center gap-1 rounded-full bg-white px-2 py-0.5 text-xs text-gray-700 ring-1 ring-gray-300">
                                            <span x-text="k.name"></span>
                                            <button type="button" @click="kontaktEntfernen(seite, k.id)" class="text-gray-400 hover:text-red-600" title="Entfernen">✕</button>
                                        </span>
                                    </template>
                                </div>
                                <div class="mt-1" @click.outside="suche[seite].treffer = []">
                                    <input type="text" x-model="suche[seite].text" @input.debounce.250ms="kontakteSuchen(seite)"
                                           placeholder="Namen tippen …" autocomplete="off"
                                           class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    {{-- Im Fluss statt absolut: Der Modal-Körper scrollt (overflow-y-auto)
                                         und würde eine absolut positionierte Liste abschneiden. --}}
                                    <ul x-show="suche[seite].treffer.length" x-cloak
                                        class="mt-1 max-h-48 w-full overflow-auto rounded-lg border border-gray-200 bg-white shadow">
                                        <template x-for="t in suche[seite].treffer" :key="t.id">
                                            <li @click="kontaktUebernehmen(seite, t)" class="cursor-pointer px-3 py-1.5 text-sm hover:bg-indigo-50">
                                                <span x-text="t.name" class="font-medium"></span>
                                                <span x-show="t.gesperrt" class="ml-1 text-xs text-red-600">gesperrt</span>
                                            </li>
                                        </template>
                                    </ul>
                                </div>
                            </section>
                        </template>
                    </div>

                    <p x-show="modal.fehler" x-cloak x-text="modal.fehler" class="mt-3 text-sm text-red-600"></p>
                </div>

                <div class="flex items-center justify-between gap-2 border-t border-gray-200 px-5 py-3">
                    <button type="button" x-show="modal.id" @click="gruppeLoeschen()"
                            class="text-sm text-gray-500 hover:text-red-600">Gruppe löschen</button>
                    <span x-show="! modal.id"></span>
                    <div class="flex gap-2">
                        <button type="button" @click="modalSchliessen()" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">Abbrechen</button>
                        <button type="button" @click="gruppeSpeichern()" :disabled="modal.laeuft"
                                class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
                            <span x-text="modal.laeuft ? 'Speichere…' : 'Speichern'"></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function newsletterEditor(config) {
            return {
                // Inhalt
                bausteine: [],
                titel: @js(old('titel', $kampagne->titel ?? '')),
                betreff: @js(old('betreff', $kampagne->betreff ?? '')),
                absenderName: @js(old('absender_name', $kampagne->absender_name ?? '')),
                antwortAn: @js(old('antwort_an', $kampagne->antwort_an ?? '')),
                kontoId: @js((string) old('mail_konto_id', $kampagne->mail_konto_id ?? '')),
                rahmen: @js((string) $rahmenWert),

                // Manuelle Gruppen (eigene) + welche davon angehakt sind.
                gruppen: config.gruppen || [],
                gewaehlteGruppen: config.gewaehlteGruppen || [],
                rollen: config.rollen || [],
                modal: { offen: false, id: null, name: '', rollen_ein: [], rollen_aus: [], user_ein: [], user_aus: [], fehler: '', laeuft: false },
                suche: { ein: { text: '', treffer: [] }, aus: { text: '', treffer: [] } },

                // Oberfläche
                reichweite: null,
                vorschauBetreff: '',
                platzhalterHinweis: '',

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
                // await-Callback sind sie undefined.
                _vorschau: null,
                _json: null,
                // Das zuletzt fokussierte Textfeld – Ziel für die Platzhalter-Knöpfe.
                _feld: null,

                init() {
                    // Jeder Baustein bekommt eine Kennung, damit Alpine beim
                    // Umsortieren die Felder mitnimmt statt sie neu zu zeichnen
                    // (sonst springt der Cursor beim Tippen).
                    this.bausteine = (config.bausteine || []).map(b => ({ ...b, _id: ++this._zaehler }));

                    this._vorschau = this.$refs.vorschau;
                    this._json = this.$refs.json;

                    this.reichweiteLaden();
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
                    if (typ === 'html') Object.assign(vorlage, { html: '' });

                    this.bausteine.push(vorlage);
                    this.nachVorschau();
                },

                entfernen(i) { this.bausteine.splice(i, 1); this._feld = null; this.nachVorschau(); },

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

                // ── Platzhalter ─────────────────────────────────────────────
                // Nur Felder merken, in denen ein Platzhalter Sinn ergibt:
                // Betreff, Textabsatz, Überschrift, Knopf-Beschriftung, HTML.
                feldMerken(event) {
                    const el = event.target;
                    if (! el) return;
                    const passt = el.tagName === 'TEXTAREA'
                        || (el.tagName === 'INPUT' && el.type === 'text');
                    if (passt) { this._feld = el; this.platzhalterHinweis = ''; }
                },

                platzhalterEinfuegen(marke) {
                    const feld = this._feld;

                    if (! feld || ! feld.isConnected) {
                        navigator.clipboard?.writeText(marke);
                        this.platzhalterHinweis = 'Erst in ein Feld klicken – der Platzhalter liegt solange in der Zwischenablage.';
                        return;
                    }

                    const von = feld.selectionStart ?? feld.value.length;
                    const bis = feld.selectionEnd ?? von;
                    const pos = von + marke.length;

                    feld.value = feld.value.slice(0, von) + marke + feld.value.slice(bis);
                    // x-model hört auf input – so landet der Wert im Baustein bzw. Betreff.
                    feld.dispatchEvent(new Event('input', { bubbles: true }));

                    // Fokus zurück ins Feld, Cursor hinter den Platzhalter – nach dem
                    // Klick, sonst holt sich der Knopf den Fokus wieder.
                    this.$nextTick(() => setTimeout(() => {
                        feld.focus();
                        feld.setSelectionRange(pos, pos);
                    }, 0));
                },

                // ── Eigene Gruppen (Modal) ──────────────────────────────────
                gruppeNeu() {
                    this.modal = { offen: true, id: null, name: '', rollen_ein: [], rollen_aus: [], user_ein: [], user_aus: [], fehler: '', laeuft: false };
                    this.suche = { ein: { text: '', treffer: [] }, aus: { text: '', treffer: [] } };
                },

                gruppeBearbeiten(g) {
                    // Kopien, damit Abbrechen nichts an der Liste ändert.
                    this.modal = {
                        offen: true, id: g.id, name: g.name,
                        rollen_ein: [...g.rollen_ein], rollen_aus: [...g.rollen_aus],
                        user_ein: g.user_ein.map(k => ({ ...k })), user_aus: g.user_aus.map(k => ({ ...k })),
                        fehler: '', laeuft: false,
                    };
                    this.suche = { ein: { text: '', treffer: [] }, aus: { text: '', treffer: [] } };
                },

                modalSchliessen() { this.modal.offen = false; },

                async kontakteSuchen(seite) {
                    const q = this.suche[seite].text.trim();
                    if (q.length < 2) { this.suche[seite].treffer = []; return; }
                    try {
                        const antwort = await fetch(config.urls.benutzer + '?q=' + encodeURIComponent(q), {
                            headers: { 'Accept': 'application/json' },
                        });
                        const treffer = await antwort.json();
                        const schon = new Set(this.modal['user_' + seite].map(k => k.id));
                        this.suche[seite].treffer = treffer.filter(t => ! schon.has(t.id));
                    } catch (e) { this.suche[seite].treffer = []; }
                },

                kontaktUebernehmen(seite, t) {
                    this.modal['user_' + seite].push({ id: t.id, name: t.name });
                    this.suche[seite] = { text: '', treffer: [] };
                },

                kontaktEntfernen(seite, id) {
                    this.modal['user_' + seite] = this.modal['user_' + seite].filter(k => k.id !== id);
                },

                async gruppeSpeichern() {
                    if (! this.modal.name.trim()) { this.modal.fehler = 'Bitte einen Namen eingeben.'; return; }
                    if (! this.modal.rollen_ein.length && ! this.modal.user_ein.length) {
                        this.modal.fehler = 'Mindestens eine Gruppe oder einen Kontakt einschließen – sonst wäre die Gruppe leer.';
                        return;
                    }

                    this.modal.laeuft = true;
                    this.modal.fehler = '';

                    const daten = {
                        name: this.modal.name,
                        rollen_ein: this.modal.rollen_ein,
                        rollen_aus: this.modal.rollen_aus,
                        user_ein: this.modal.user_ein.map(k => k.id),
                        user_aus: this.modal.user_aus.map(k => k.id),
                    };
                    const url = this.modal.id ? config.urls.gruppen + '/' + this.modal.id : config.urls.gruppen;
                    const antwort = await this.holen(url, daten, this.modal.id ? 'PUT' : 'POST');

                    this.modal.laeuft = false;

                    if (! antwort || ! antwort.gruppe) {
                        this.modal.fehler = antwort?.message ?? 'Speichern fehlgeschlagen.';
                        return;
                    }

                    const i = this.gruppen.findIndex(g => g.id === antwort.gruppe.id);
                    if (i >= 0) {
                        this.gruppen.splice(i, 1, antwort.gruppe);
                    } else {
                        this.gruppen.push(antwort.gruppe);
                        this.gruppen.sort((a, b) => a.name.localeCompare(b.name, 'de'));
                        // Eine neue Gruppe will man in der Regel sofort anschreiben.
                        if (! this.gewaehlteGruppen.includes(antwort.gruppe.kennung)) this.gewaehlteGruppen.push(antwort.gruppe.kennung);
                    }

                    this.modal.offen = false;
                    this.$nextTick(() => this.reichweiteLaden());
                },

                async gruppeLoeschen() {
                    if (! this.modal.id) return;
                    if (! confirm('Gruppe „' + this.modal.name + '“ löschen? Ausgaben, die sie nutzen, verlieren diese Zielgruppe.')) return;

                    const antwort = await this.holen(config.urls.gruppen + '/' + this.modal.id, {}, 'DELETE');
                    if (! antwort?.ok) { this.modal.fehler = 'Löschen fehlgeschlagen.'; return; }

                    const kennung = 'gruppe:' + this.modal.id;
                    this.gruppen = this.gruppen.filter(g => g.id !== this.modal.id);
                    this.gewaehlteGruppen = this.gewaehlteGruppen.filter(k => k !== kennung);
                    this.modal.offen = false;
                    this.$nextTick(() => this.reichweiteLaden());
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

                // ── Absender ────────────────────────────────────────────────
                // Dropdown gewechselt: Name und Antwort-an mit den Vorgaben des
                // gewählten Kontos ersetzen (Standard = Werte der Instanz). Beide
                // Felder bleiben danach frei editierbar.
                kontoGewechselt() {
                    const konto = config.konten.find(k => k.id === this.kontoId);
                    this.absenderName = konto ? konto.name : config.standardName;
                    this.antwortAn = konto ? konto.antwort : '';
                },

                // ── Vorschau ────────────────────────────────────────────────
                formularwerte() {
                    return {
                        titel: this.titel,
                        betreff: this.betreff,
                        absender_name: this.absenderName,
                        antwort_an: this.antwortAn,
                        mail_konto_id: this.kontoId,
                        rahmen: this.rahmen,
                        bausteine: JSON.stringify(this.bausteine),
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
                    if (this._vorschau) this._vorschau.srcdoc = fertig.html;
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

                async holen(url, daten, methode = 'POST') {
                    try {
                        const antwort = await fetch(url, {
                            method: methode,
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
                    // Das versteckte Feld auf den aktuellen Stand bringen.
                    this._json.value = JSON.stringify(this.bausteine);
                },
            };
        }
    </script>
    @endpush
</x-app-layout>
