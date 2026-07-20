{{-- Status-Abzeichen einer Ausgabe. Erwartet $status. --}}
@php
    $anzeige = match ($status) {
        'entwurf' => ['Entwurf', 'bg-gray-100 text-gray-600'],
        'versand' => ['Versand läuft', 'bg-amber-100 text-amber-800'],
        'versendet' => ['Versendet', 'bg-green-100 text-green-800'],
        default => [$status, 'bg-gray-100 text-gray-600'],
    };
@endphp

<span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $anzeige[1] }}">{{ $anzeige[0] }}</span>
