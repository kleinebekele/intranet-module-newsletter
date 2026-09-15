<?php

namespace Intranet\Modules\Newsletter\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Intranet\Modules\Newsletter\Models\Gruppe;
use Intranet\Modules\Newsletter\Support\Empfaengerkreis;

/**
 * Persönliche Zielgruppen („manuelle Gruppen") – angelegt und gepflegt aus dem
 * Modal im Ausgabe-Formular, deshalb durchweg JSON.
 *
 * Jede Gruppe gehört ihrem Ersteller; ein anderer Benutzer sieht sie nur, wenn
 * eine Ausgabe sie bereits nutzt (dort schreibgeschützt).
 */
class GruppeController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $daten = $this->daten($request);

        $gruppe = Gruppe::create($daten + ['user_id' => $request->user()->id]);

        return response()->json(['gruppe' => $gruppe->fuerFormular(), 'uebersicht' => $this->uebersicht($gruppe)]);
    }

    public function update(Request $request, Gruppe $gruppe): JsonResponse
    {
        $this->eigene($request, $gruppe);

        $gruppe->update($this->daten($request));

        return response()->json(['gruppe' => $gruppe->fuerFormular(), 'uebersicht' => $this->uebersicht($gruppe)]);
    }

    public function destroy(Request $request, Gruppe $gruppe): JsonResponse
    {
        $this->eigene($request, $gruppe);

        $gruppe->delete();

        // Ausgaben, die die Gruppe noch nennen, verlieren den Eintrag beim
        // nächsten Speichern (unbekannte Zielgruppen werden verworfen); bis
        // dahin löst der Empfängerkreis die fehlende Gruppe zu niemandem auf.
        return response()->json(['ok' => true]);
    }

    /**
     * Kontakt-Suche für das Modal: Name oder Mailadresse, höchstens zehn Treffer.
     */
    public function benutzerSuche(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $treffer = User::query()
            ->where(fn ($w) => $w->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%"))
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'email', 'gesperrt_am'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => (string) $u->email,
                'gesperrt' => $u->gesperrt_am !== null,
            ]);

        return response()->json($treffer);
    }

    /** @return array{name: string, regeln: array<string, mixed>} */
    private function daten(Request $request): array
    {
        $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'rollen_ein' => ['array'],
            'rollen_aus' => ['array'],
            'user_ein' => ['array'],
            'user_aus' => ['array'],
        ]);

        $regeln = Gruppe::regelnBereinigen($request->only(['rollen_ein', 'user_ein', 'rollen_aus', 'user_aus']));

        // Nur Rollen und Kontakte, die es gibt.
        $rollen = Role::whereIn('role_id', array_merge($regeln['rollen_ein'], $regeln['rollen_aus']))->pluck('role_id')->all();
        $benutzer = User::whereIn('id', array_merge($regeln['user_ein'], $regeln['user_aus']))->pluck('id')->all();

        $regeln['rollen_ein'] = array_values(array_intersect($regeln['rollen_ein'], $rollen));
        $regeln['rollen_aus'] = array_values(array_intersect($regeln['rollen_aus'], $rollen));
        $regeln['user_ein'] = array_values(array_intersect($regeln['user_ein'], $benutzer));
        $regeln['user_aus'] = array_values(array_intersect($regeln['user_aus'], $benutzer));

        return [
            'name' => trim((string) $request->input('name')),
            'regeln' => $regeln,
        ];
    }

    private function eigene(Request $request, Gruppe $gruppe): void
    {
        abort_unless($gruppe->user_id === $request->user()->id, 403, 'Diese Gruppe gehört einem anderen Benutzer.');
    }

    /** @return array{gesamt: int, gesperrt: int, unzustellbar: int, erreichbar: int} */
    private function uebersicht(Gruppe $gruppe): array
    {
        return Empfaengerkreis::uebersicht([$gruppe->kennung()]);
    }
}
