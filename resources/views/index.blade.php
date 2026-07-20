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

    <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white">
        <table class="w-full text-sm">
            <thead class="border-b border-gray-200 bg-gray-50 text-left text-xs uppercase tracking-wide text-gray-500">
                <tr>
                    <th class="px-4 py-3 font-semibold">Ausgabe</th>
                    <th class="px-4 py-3 font-semibold">Betreff</th>
                    <th class="px-4 py-3 font-semibold">Status</th>
                    <th class="px-4 py-3 font-semibold">Empfänger</th>
                    <th class="px-4 py-3 font-semibold">Angelegt</th>
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
                            @include('newsletter::partials.status', ['status' => $kampagne->status])
                        </td>
                        <td class="px-4 py-3 text-gray-600">
                            @if ($kampagne->istEntwurf())
                                <span class="text-gray-400">–</span>
                            @else
                                {{ $kampagne->eingeliefert_count }} von {{ $kampagne->empfaenger_count }}
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-500">{{ $kampagne->created_at?->format('d.m.Y') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-10 text-center text-gray-500">
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
</x-app-layout>
