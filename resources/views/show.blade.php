<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <h1 class="text-xl font-semibold text-gray-800">{{ $kampagne->titel }}</h1>
                @include('newsletter::partials.status', ['status' => $kampagne->status])
            </div>
            <a href="{{ route('module.newsletter.index') }}"
               class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700">
                <x-module-icon name="back" class="text-base" />
                Zurück
            </a>
        </div>
    </x-slot>

    @if (session('error'))
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            {{ session('error') }}
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-3">

        {{-- ── Die Ausgabe, so wie sie ankommt ───────────────────────────── --}}
        <section class="rounded-xl border border-gray-200 bg-white p-5 xl:col-span-2">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Vorschau</h2>
            <p class="mt-1 text-sm text-gray-500">Betreff: <span class="text-gray-800">{{ $kampagne->betreff }}</span></p>

            <div class="mt-4 rounded-lg border border-gray-200 bg-gray-50 p-4">
                {{-- Der Inhalt ist beim Bauen maskiert worden (siehe Bausteine), das
                     hier ist genau das HTML, das auch in der Mail steht. --}}
                <div class="prose prose-sm max-w-none">{!! $kampagne->alsHtml() !!}</div>
            </div>

            @if (($kampagne->bausteine ?? []) === [])
                <p class="mt-3 text-sm text-gray-500">Diese Ausgabe hat noch keinen Inhalt.</p>
            @endif
        </section>

        {{-- ── Steuerung ─────────────────────────────────────────────────── --}}
        <div class="space-y-6">

            <section class="rounded-xl border border-gray-200 bg-white p-5">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Zielgruppen</h2>

                <div class="mt-2 flex flex-wrap gap-1.5">
                    @forelse ($kampagne->zielgruppen ?? [] as $gruppe)
                        <span class="rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700">
                            {{ $gruppe === 'alle' ? 'Alle Benutzer' : $gruppe }}
                        </span>
                    @empty
                        <span class="text-sm text-gray-500">Keine ausgewählt.</span>
                    @endforelse
                </div>

                @if ($uebersicht)
                    <div class="mt-4 rounded-lg bg-gray-50 p-3 text-sm">
                        <p class="font-medium text-gray-800">
                            Erreicht {{ $uebersicht['erreichbar'] }} von {{ $uebersicht['gesamt'] }} Personen
                        </p>
                        <ul class="mt-1 space-y-0.5 text-xs text-gray-500">
                            @if ($uebersicht['gesperrt'] > 0)
                                <li>{{ $uebersicht['gesperrt'] }} gesperrt</li>
                            @endif
                            @if ($uebersicht['unzustellbar'] > 0)
                                <li>{{ $uebersicht['unzustellbar'] }} ohne echte Mailadresse</li>
                            @endif
                        </ul>
                    </div>
                @endif
            </section>

            @if ($kampagne->istEntwurf())
                <section class="rounded-xl border border-indigo-200 bg-indigo-50/50 p-5">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-indigo-700">Freigabe</h2>
                    <p class="mt-1 text-sm text-gray-600">
                        Danach ist die Ausgabe unveränderlich und der Versand läuft an –
                        gedrosselt über den Ausgangskorb.
                    </p>

                    <form method="POST" action="{{ route('module.newsletter.freigeben', $kampagne) }}"
                          class="mt-3"
                          onsubmit="return confirm('Diese Ausgabe jetzt an {{ $uebersicht['erreichbar'] ?? 0 }} Empfänger freigeben? Das lässt sich nicht zurücknehmen.');">
                        @csrf
                        <button type="submit"
                                @disabled(($uebersicht['erreichbar'] ?? 0) === 0 || ($kampagne->bausteine ?? []) === [])
                                class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-40">
                            Jetzt freigeben und verschicken
                        </button>
                    </form>

                    <div class="mt-3 flex items-center justify-between">
                        <a href="{{ route('module.newsletter.edit', $kampagne) }}"
                           class="text-sm text-indigo-700 hover:underline">Bearbeiten</a>

                        <form method="POST" action="{{ route('module.newsletter.destroy', $kampagne) }}"
                              onsubmit="return confirm('Diesen Entwurf löschen?');">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-sm text-gray-500 hover:text-red-600">Löschen</button>
                        </form>
                    </div>
                </section>
            @else
                <section class="rounded-xl border border-gray-200 bg-white p-5">
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Versand</h2>

                    @php
                        $anteil = $fortschritt['gesamt'] > 0
                            ? round(($fortschritt['eingeliefert'] + $fortschritt['uebersprungen'] + $fortschritt['fehler'])
                                / $fortschritt['gesamt'] * 100)
                            : 0;
                    @endphp

                    <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-gray-100">
                        <div class="h-full rounded-full bg-indigo-600" style="width: {{ $anteil }}%"></div>
                    </div>

                    <dl class="mt-3 space-y-1 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Zugestellt</dt>
                            <dd class="font-medium text-gray-800">{{ $fortschritt['eingeliefert'] }}</dd>
                        </div>
                        @if ($fortschritt['wartend'] > 0)
                            <div class="flex justify-between">
                                <dt class="text-gray-500">Noch offen</dt>
                                <dd class="font-medium text-amber-700">{{ $fortschritt['wartend'] }}</dd>
                            </div>
                        @endif
                        @if ($fortschritt['uebersprungen'] > 0)
                            <div class="flex justify-between">
                                <dt class="text-gray-500">Übersprungen</dt>
                                <dd class="font-medium text-gray-600">{{ $fortschritt['uebersprungen'] }}</dd>
                            </div>
                        @endif
                        @if ($fortschritt['fehler'] > 0)
                            <div class="flex justify-between">
                                <dt class="text-gray-500">Fehlgeschlagen</dt>
                                <dd class="font-medium text-red-700">{{ $fortschritt['fehler'] }}</dd>
                            </div>
                        @endif
                    </dl>

                    <p class="mt-3 text-xs text-gray-500">
                        „Zugestellt" heißt: im Ausgangskorb eingeliefert. Wann sie den Server verlassen,
                        bestimmt das Stundenlimit unter Einstellungen&nbsp;→&nbsp;Mailversand.
                    </p>

                    @if ($kampagne->freigegeben_am)
                        <p class="mt-2 text-xs text-gray-400">
                            Freigegeben am {{ $kampagne->freigegeben_am->format('d.m.Y H:i') }}
                        </p>
                    @endif
                </section>
            @endif
        </div>
    </div>

    @if ($auffaellige->isNotEmpty())
        <section class="mt-6 rounded-xl border border-gray-200 bg-white p-5">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Nicht zugestellt</h2>
            <p class="mt-1 text-sm text-gray-500">
                Wer die Ausgabe nicht bekommen hat – und warum.
            </p>

            <div class="mt-3 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b border-gray-200 text-left text-xs uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="py-2 font-semibold">Person</th>
                            <th class="py-2 font-semibold">Adresse</th>
                            <th class="py-2 font-semibold">Grund</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($auffaellige as $empfaenger)
                            <tr>
                                <td class="py-2 text-gray-800">{{ $empfaenger->user?->name ?? '—' }}</td>
                                <td class="py-2 text-gray-500">{{ $empfaenger->email }}</td>
                                <td class="py-2 text-gray-600">{{ $empfaenger->grund }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</x-app-layout>
