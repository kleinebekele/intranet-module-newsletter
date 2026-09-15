<?php

namespace Intranet\Modules\Newsletter\Support;

use App\Models\Role;
use App\Models\User;
use App\Support\Zustellbarkeit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Intranet\Modules\Newsletter\Models\Gruppe;

/**
 * Wer bekommt diese Ausgabe?
 *
 * Bewusst über die Rollen, die es in dieser Instanz gerade gibt – das Modul
 * bringt keine eigenen mit und kennt keine fest verdrahteten Gruppen. In der
 * Schule sind das etwa `teacher`/`parent`/`staff`/`student` (von einem
 * Benutzer-Import angelegt), anderswo etwas ganz anderes. Wer eine neue Rolle
 * im Admin-Panel anlegt, kann sie sofort anschreiben.
 */
class Empfaengerkreis
{
    /** Sonderfall „alle Benutzer", unabhängig von Rollen. */
    public const ALLE = 'alle';

    /**
     * Die typischen Personengruppen eines Benutzer-Imports, in der Reihenfolge,
     * in der man sie im Formular erwartet: erst das Haus, dann die Angeschriebenen.
     * Alle übrigen Rollen folgen alphabetisch dahinter.
     */
    private const REIHENFOLGE = ['staff', 'teacher', 'parent', 'student'];

    /**
     * Die auswählbaren Zielgruppen für das Formular.
     *
     * @return Collection<int, Role>
     */
    public static function rollen(): Collection
    {
        $rang = array_flip(self::REIHENFOLGE);

        return Role::query()->orderBy('name')->get()
            ->sortBy(fn (Role $r) => sprintf('%02d %s', $rang[$r->role_id] ?? 99, $r->name))
            ->values();
    }

    /**
     * Die tatsächlich anschreibbaren Empfänger.
     *
     * @param  array<int, string>  $zielgruppen
     * @return Collection<int, User>
     */
    public static function erreichbare(array $zielgruppen): Collection
    {
        return self::basis($zielgruppen)
            ->whereNull('gesperrt_am')
            ->orderBy('id')
            ->get(['id', 'name', 'email'])
            ->filter(fn (User $u) => Zustellbarkeit::zustellbar((string) $u->email))
            ->values();
    }

    /**
     * Die Zahlen für die Anzeige im Formular – VOR dem Absenden.
     *
     * Ohne diese Aufschlüsselung wundert man sich hinterher, warum aus 917
     * ausgewählten Personen 424 Mails wurden. Die Differenz hat immer einen
     * nachvollziehbaren Grund; der gehört dorthin, wo die Auswahl passiert.
     *
     * @param  array<int, string>  $zielgruppen
     * @return array{gesamt: int, gesperrt: int, unzustellbar: int, erreichbar: int}
     */
    public static function uebersicht(array $zielgruppen): array
    {
        $alle = self::basis($zielgruppen)->get(['id', 'email', 'gesperrt_am']);

        $gesperrt = $alle->filter(fn (User $u) => $u->gesperrt_am !== null);
        $offen = $alle->filter(fn (User $u) => $u->gesperrt_am === null);
        $unzustellbar = $offen->reject(fn (User $u) => Zustellbarkeit::zustellbar((string) $u->email));

        return [
            'gesamt' => $alle->count(),
            'gesperrt' => $gesperrt->count(),
            'unzustellbar' => $unzustellbar->count(),
            'erreichbar' => $offen->count() - $unzustellbar->count(),
        ];
    }

    /**
     * Die Grundmenge: alle Benutzer der gewählten Rollen, noch ungefiltert.
     *
     * @param  array<int, string>  $zielgruppen
     * @return Builder<User>
     */
    private static function basis(array $zielgruppen): Builder
    {
        $abfrage = User::query();

        if (in_array(self::ALLE, $zielgruppen, true)) {
            return $abfrage;
        }

        $eintraege = array_values(array_filter($zielgruppen, fn ($z) => is_string($z) && $z !== ''));

        // Manuelle Gruppen (`gruppe:<id>`) von den Rollen trennen.
        $gruppenIds = array_values(array_filter(array_map(fn ($z) => Gruppe::idAus($z), $eintraege)));
        $rollen = array_values(array_filter($eintraege, fn ($z) => Gruppe::idAus($z) === null));
        $gruppen = $gruppenIds === [] ? collect() : Gruppe::whereIn('id', $gruppenIds)->get();

        if ($rollen === [] && $gruppen->isEmpty()) {
            // Keine Auswahl heißt keine Empfänger – nicht etwa „alle".
            return $abfrage->whereRaw('1 = 0');
        }

        // Rollen ODER eine der Gruppen – jede Gruppe bringt ihre eigenen
        // Ein-/Ausschlüsse mit; ein Ausschluss gilt nur innerhalb SEINER Gruppe.
        return $abfrage->where(function (Builder $q) use ($rollen, $gruppen): void {
            if ($rollen !== []) {
                $q->orWhereHas('roles', fn (Builder $x) => $x->whereIn('roles.role_id', $rollen));
            }
            foreach ($gruppen as $gruppe) {
                $q->orWhere(fn (Builder $x) => $gruppe->anwenden($x));
            }
        });
    }
}
