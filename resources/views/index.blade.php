<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold text-gray-800">Newsletter</h1>
            <a href="{{ route('module.newsletter.create') }}"
               class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                <x-module-icon name="plus" class="text-base" />
                Neue Ausgabe
            </a>
        </div>
    </x-slot>

    @if (session('error'))
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            {{ session('error') }}
        </div>
    @endif

    <div x-data="empfaengerModal()" @keydown.escape.window="offen = false">
    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white">
        <table class="w-full text-sm">
            <thead class="border-b border-gray-200 bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                <tr>
                    <th class="px-4 py-3 font-semibold">Ausgabe</th>
                    <th class="px-4 py-3 font-semibold">Betreff</th>
                    <th class="px-4 py-3 font-semibold">Status</th>
                    <th class="px-4 py-3 font-semibold">Zielgruppen</th>
                    <th class="px-4 py-3 font-semibold">Empfänger</th>
                    <th class="px-4 py-3 font-semibold">Angelegt</th>
                    <th class="px-4 py-3 font-semibold">Versendet</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($kampagnen as $kampagne)
                    <tr class="hover:bg-indigo-50/40">
                        <td class="px-4 py-3">
                            <a href="{{ route('module.newsletter.show', $kampagne) }}"
                               class="font-medium text-indigo-700 hover:underline">{{ $kampagne->titel }}</a>
                            @if ($kampagne->ersteller)
                                <span class="block text-xs text-gray-400">von {{ $kampagne->ersteller->name }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ $kampagne->betreff }}</td>
                        <td class="px-4 py-3">
                            @include('newsletter::partials.status', ['status' => $kampagne->status, 'versandAb' => $kampagne->versand_ab])
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-1">
                                @forelse ($kampagne->zielgruppen ?? [] as $gruppe)
                                    <span class="rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700 whitespace-nowrap">
                                        {{ $gruppe === 'alle' ? 'Alle Benutzer' : ($zielgruppenNamen[$gruppe] ?? $gruppe) }}
                                    </span>
                                @empty
                                    <span class="text-gray-400">–</span>
                                @endforelse
                            </div>
                        </td>
                        <td class="px-4 py-3 text-gray-600 whitespace-nowrap">
                            @if ($kampagne->istEntwurf())
                                @if ($r = $reichweite[$kampagne->id] ?? null)
                                    <span @class(['font-medium text-amber-700' => $r['erreichbar'] === 0])>0 von {{ $r['erreichbar'] }}</span>
                                    <span class="block text-xs text-gray-400">wenn jetzt freigegeben</span>
                                @endif
                            @else
                                @php
                                    $zu = \Intranet\Modules\Newsletter\Http\Controllers\NewsletterController::zustellUebersicht($kampagne);
                                @endphp
                                <button type="button" title="Empfänger und Zustellung ansehen"
                                        @click="oeffnen(@js(route('module.newsletter.empfaenger', $kampagne)), @js($kampagne->titel))"
                                        class="text-left hover:underline">
                                    <span class="text-indigo-700">{{ $kampagne->eingeliefert_count }} von {{ $kampagne->empfaenger_count }}</span>
                                    @if ($zu && ($zu['verzoegert'] || $zu['abgewiesen']))
                                        <span class="block text-xs font-medium {{ $zu['abgewiesen'] ? 'text-red-700' : 'text-amber-700' }}">
                                            {{ $zu['verzoegert'] + $zu['abgewiesen'] }} nicht zugestellt
                                        </span>
                                    @endif
                                </button>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-500">{{ $kampagne->created_at?->format('d.m.Y') }}</td>
                        <td class="px-4 py-3 text-gray-500 whitespace-nowrap" title="Zeitpunkt der ersten Mail">
                            @if ($kampagne->erste_einlieferung)
                                {{ \Illuminate\Support\Carbon::parse($kampagne->erste_einlieferung)->format('d.m.Y H:i') }}
                            @else
                                <span class="text-gray-400">–</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('module.newsletter.show', $kampagne) }}"
                               class="text-sm text-indigo-700 hover:underline">Vorschau</a>
                            @if ($kampagne->istEntwurf())
                                <a href="{{ route('module.newsletter.edit', $kampagne) }}"
                                   class="ml-3 text-sm text-indigo-700 hover:underline">Bearbeiten</a>
                                <form method="POST" action="{{ route('module.newsletter.destroy', $kampagne) }}"
                                      class="ml-3 inline"
                                      onsubmit="return confirm('Entwurf „{{ $kampagne->titel }}“ löschen?');">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-sm text-gray-500 hover:text-red-600">Löschen</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-10 text-center text-gray-500">
                            Noch keine Ausgabe. Lege die erste an – verschickt wird erst nach ausdrücklicher Freigabe.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $kampagnen->links() }}
    </div>

    {{-- ── Modal: Empfänger einer Ausgabe mit Zustellstatus ─────────────── --}}
    <div x-show="offen" x-cloak class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-gray-900/50 p-4 sm:p-8"
         @click.self="offen = false">
        <div class="w-full max-w-5xl rounded-xl bg-white shadow-xl">
            <div class="flex items-center justify-between border-b border-gray-200 px-5 py-3">
                <h2 class="font-semibold text-gray-800">
                    Empfänger <span class="font-normal text-gray-500" x-text="'– ' + titel"></span>
                </h2>
                <button type="button" @click="offen = false" class="rounded-md p-1 text-xl text-gray-400 hover:bg-gray-100 hover:text-gray-600" title="Schließen">
                    <i class='bx bx-x'></i>
                </button>
            </div>

            <div class="space-y-3 px-5 py-4">
                <p x-show="laden" class="text-sm text-gray-500">Lädt …</p>
                <p x-show="fehler" class="text-sm text-red-700" x-text="fehler"></p>

                <template x-if="! laden && ! fehler">
                    <div class="space-y-3">
                        {{-- Status-Filter: Mehrfachauswahl, nichts gewählt = alle --}}
                        <div class="flex flex-wrap items-center gap-1.5">
                            <template x-for="s in statusListe()" :key="s.label">
                                <button type="button" @click="umschalten(s.label)"
                                        class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-medium"
                                        :class="aktiv.includes(s.label) ? farbe(s.farbe) + ' border-transparent ring-2 ring-indigo-400' : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50'">
                                    <span x-text="s.label"></span>
                                    <span class="opacity-70" x-text="s.anzahl"></span>
                                </button>
                            </template>
                            <button type="button" x-show="aktiv.length" @click="aktiv = []"
                                    class="px-1 text-xs text-gray-500 hover:text-gray-700">alle zeigen</button>
                        </div>

                        {{-- Zielgruppen-Filter: nur wenn die Ausgabe an mehrere Rollen ging --}}
                        <div class="flex flex-wrap items-center gap-1.5" x-show="gruppenListe().length > 1">
                            <span class="mr-1 text-xs text-gray-500">Zielgruppe:</span>
                            <template x-for="g in gruppenListe()" :key="g.name">
                                <button type="button" @click="gruppeUmschalten(g.name)"
                                        class="inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-medium"
                                        :class="gruppen.includes(g.name) ? 'border-transparent bg-indigo-100 text-indigo-700 ring-2 ring-indigo-400' : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50'">
                                    <span x-text="g.name"></span>
                                    <span class="opacity-70" x-text="g.anzahl"></span>
                                </button>
                            </template>
                            <button type="button" x-show="gruppen.length" @click="gruppen = []"
                                    class="px-1 text-xs text-gray-500 hover:text-gray-700">alle zeigen</button>
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <input type="search" x-model="suche" placeholder="Name oder Mailadresse (ab 3 Zeichen)"
                                   class="min-w-[16rem] flex-1 rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <span class="text-xs text-gray-500" x-text="gefiltert().length + ' von ' + zeilen.length"></span>
                        </div>

                        <div class="max-h-[60vh] overflow-auto rounded-lg border border-gray-200">
                            <table class="w-full text-sm">
                                <thead class="sticky top-0 border-b border-gray-200 bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                                    <tr>
                                        <th class="px-3 py-2 font-semibold">Person</th>
                                        <th class="px-3 py-2 font-semibold">Adresse</th>
                                        <th class="px-3 py-2 font-semibold" x-show="gruppenListe().length > 1">Zielgruppe</th>
                                        <th class="px-3 py-2 font-semibold">Status</th>
                                        <th class="px-3 py-2 font-semibold whitespace-nowrap">Zeitpunkt</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <template x-for="(z, i) in gefiltert()" :key="i">
                                        <tr class="align-top">
                                            <td class="px-3 py-2 text-gray-800" x-text="z.name"></td>
                                            <td class="px-3 py-2 text-gray-500" x-text="z.email"></td>
                                            <td class="px-3 py-2 text-xs text-gray-600" x-show="gruppenListe().length > 1" x-text="(z.gruppen || []).join(', ')"></td>
                                            <td class="px-3 py-2">
                                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium" :class="farbe(z.farbe)" x-text="z.label"></span>
                                                <span x-show="z.detail" class="mt-1 block text-xs text-gray-500" x-text="z.detail"></span>
                                            </td>
                                            <td class="px-3 py-2 text-gray-500 whitespace-nowrap" x-text="z.zeit || '—'"></td>
                                        </tr>
                                    </template>
                                    <tr x-show="gefiltert().length === 0">
                                        <td colspan="5" class="px-3 py-6 text-center text-gray-500">Keine Empfänger für diese Auswahl.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
    </div>

    <script>
        function empfaengerModal() {
            const FARBEN = {
                green: 'bg-green-100 text-green-800',
                amber: 'bg-amber-100 text-amber-800',
                red: 'bg-red-100 text-red-700',
                gray: 'bg-gray-100 text-gray-600',
                indigo: 'bg-indigo-100 text-indigo-700',
            };

            return {
                offen: false,
                laden: false,
                fehler: '',
                titel: '',
                zeilen: [],
                aktiv: [],
                gruppen: [],
                suche: '',

                async oeffnen(url, titel) {
                    this.offen = true;
                    this.titel = titel;
                    this.zeilen = [];
                    this.aktiv = [];
                    this.gruppen = [];
                    this.suche = '';
                    this.fehler = '';
                    this.laden = true;
                    try {
                        const antwort = await fetch(url, { headers: { Accept: 'application/json' } });
                        if (! antwort.ok) throw new Error();
                        this.zeilen = (await antwort.json()).zeilen;
                    } catch (e) {
                        this.fehler = 'Die Empfänger konnten nicht geladen werden.';
                    }
                    this.laden = false;
                },

                farbe(f) {
                    return FARBEN[f] || FARBEN.gray;
                },

                // Die vorkommenden Status mit Anzahl – Reihenfolge wie im ersten Auftreten.
                statusListe() {
                    const liste = [];
                    for (const z of this.zeilen) {
                        const s = liste.find(x => x.label === z.label);
                        s ? s.anzahl++ : liste.push({ label: z.label, farbe: z.farbe, anzahl: 1 });
                    }
                    return liste;
                },

                umschalten(label) {
                    this.aktiv = this.aktiv.includes(label)
                        ? this.aktiv.filter(l => l !== label)
                        : [...this.aktiv, label];
                },

                gruppenListe() {
                    const liste = [];
                    for (const z of this.zeilen) {
                        for (const name of (z.gruppen || [])) {
                            const g = liste.find(x => x.name === name);
                            g ? g.anzahl++ : liste.push({ name, anzahl: 1 });
                        }
                    }
                    return liste;
                },

                gruppeUmschalten(name) {
                    this.gruppen = this.gruppen.includes(name)
                        ? this.gruppen.filter(n => n !== name)
                        : [...this.gruppen, name];
                },

                gefiltert() {
                    const q = this.suche.trim().toLowerCase();
                    return this.zeilen.filter(z =>
                        (this.aktiv.length === 0 || this.aktiv.includes(z.label))
                        && (this.gruppen.length === 0 || (z.gruppen || []).some(g => this.gruppen.includes(g)))
                        && (q.length < 3 || (z.email || '').toLowerCase().includes(q) || (z.name || '').toLowerCase().includes(q)));
                },
            };
        }
    </script>
</x-app-layout>
