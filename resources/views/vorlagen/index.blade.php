<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold text-gray-800">Mailvorlagen</h1>
            <a href="{{ route('module.newsletter.vorlagen.create') }}"
               class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                <x-module-icon name="plus" class="text-base" />
                Neue Vorlage
            </a>
        </div>
    </x-slot>

    @if (session('error'))
        <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            {{ session('error') }}
        </div>
    @endif

    <p class="mb-4 text-sm text-gray-600">
        Eine Vorlage ist der <strong>Rahmen</strong> um jede Ausgabe: Kopf, Logo, Farben, Fußzeile.
        Solange hier nichts angelegt ist oder eine Ausgabe keine Vorlage wählt, gilt der Rahmen aus
        <em>Verwaltung → Mailvorlagen</em>. Anrede und Abbinder kommen weiterhin von dort.
    </p>

    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white">
        <table class="w-full text-sm">
            <thead class="border-b border-gray-200 bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                <tr>
                    <th class="px-4 py-3 font-semibold">Vorlage</th>
                    <th class="px-4 py-3 font-semibold">Verwendet von</th>
                    <th class="px-4 py-3 font-semibold">Geändert</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($vorlagen as $vorlage)
                    <tr class="hover:bg-indigo-50/40">
                        <td class="px-4 py-3">
                            <a href="{{ route('module.newsletter.vorlagen.edit', $vorlage) }}"
                               class="font-medium text-indigo-700 hover:underline">{{ $vorlage->name }}</a>
                            @if ($vorlage->ist_standard)
                                <span class="ml-2 rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">Standard für neue Ausgaben</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-600">
                            {{ $vorlage->kampagnen_count }} {{ $vorlage->kampagnen_count === 1 ? 'Ausgabe' : 'Ausgaben' }}
                        </td>
                        <td class="px-4 py-3 text-gray-500">{{ $vorlage->updated_at?->format('d.m.Y H:i') }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('module.newsletter.vorlagen.edit', $vorlage) }}"
                               class="text-sm text-indigo-700 hover:underline">Bearbeiten</a>
                            @unless ($vorlage->ist_standard)
                                <form method="POST" action="{{ route('module.newsletter.vorlagen.standard', $vorlage) }}" class="ml-3 inline">
                                    @csrf
                                    <button type="submit" class="text-sm text-gray-500 hover:text-indigo-700">Als Standard</button>
                                </form>
                            @endunless
                            <form method="POST" action="{{ route('module.newsletter.vorlagen.destroy', $vorlage) }}"
                                  class="ml-3 inline"
                                  onsubmit="return confirm('Vorlage „{{ $vorlage->name }}“ löschen? Ausgaben, die sie nutzen, fallen auf den Rahmen aus der Verwaltung zurück.');">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-sm text-gray-500 hover:text-red-600">Löschen</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-10 text-center text-gray-500">
                            Noch keine eigene Vorlage. Bis dahin gilt für alle Ausgaben der Rahmen aus Verwaltung → Mailvorlagen.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-app-layout>
