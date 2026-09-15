{{-- Status-Abzeichen einer Ausgabe. Erwartet $status, optional $versandAb
     (Carbon|null): freigegeben mit Termin in der Zukunft = „Geplant". --}}
@php
    $versandAb = $versandAb ?? null;
    $geplant = $status === 'versand' && $versandAb !== null && $versandAb->isFuture();

    $anzeige = match (true) {
        $geplant => ['Geplant ab '.$versandAb->format('d.m. H:i'), 'bg-sky-100 text-sky-800'],
        $status === 'entwurf' => ['Entwurf', 'bg-gray-100 text-gray-600'],
        $status === 'versand' => ['Versand läuft', 'bg-amber-100 text-amber-800'],
        $status === 'versendet' => ['Versendet', 'bg-green-100 text-green-800'],
        default => [$status, 'bg-gray-100 text-gray-600'],
    };
@endphp

<span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $anzeige[1] }}">{{ $anzeige[0] }}</span>
