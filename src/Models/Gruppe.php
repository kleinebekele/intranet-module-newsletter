<?php

namespace Intranet\Modules\Newsletter\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eine persönliche Zielgruppe („manuelle Gruppe") eines Benutzers.
 *
 * Regeln: Rollen und einzelne Kontakte EINSCHLIESSEN, Rollen und Kontakte
 * AUSSCHLIESSEN. Ausschluss gewinnt. Wer nichts einschließt, hat eine leere
 * Gruppe – nicht etwa „alle".
 *
 * In einer Ausgabe steht die Gruppe als Zielgruppe `gruppe:<id>`; der
 * {@see \Intranet\Modules\Newsletter\Support\Empfaengerkreis} löst das auf.
 */
class Gruppe extends Model
{
    /** Präfix in `newsletter_kampagnen.zielgruppen`. */
    public const PRAEFIX = 'gruppe:';

    protected $table = 'newsletter_gruppen';

    protected $fillable = ['user_id', 'name', 'regeln'];

    protected $casts = [
        'regeln' => 'array',
    ];

    public function besitzer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Nur die Gruppen dieses Benutzers – andere sieht er nie. */
    public function scopeVon(Builder $query, User $user): Builder
    {
        return $query->where('user_id', $user->id);
    }

    public function kennung(): string
    {
        return self::PRAEFIX.$this->id;
    }

    /** Ist der Zielgruppen-Eintrag eine manuelle Gruppe? Dann ihre ID, sonst null. */
    public static function idAus(string $zielgruppe): ?int
    {
        if (! str_starts_with($zielgruppe, self::PRAEFIX)) {
            return null;
        }

        $rest = substr($zielgruppe, strlen(self::PRAEFIX));

        return ctype_digit($rest) ? (int) $rest : null;
    }

    /**
     * Rohe Regeln aus dem Formular in die gespeicherte Form bringen: nur
     * Strings bzw. Ganzzahlen, ohne Dubletten, unbekannte Schlüssel weg.
     *
     * @param  array<string, mixed>  $roh
     * @return array{rollen_ein: array<int, string>, user_ein: array<int, int>, rollen_aus: array<int, string>, user_aus: array<int, int>}
     */
    public static function regelnBereinigen(array $roh): array
    {
        $rollen = fn ($liste) => array_values(array_unique(array_filter(
            array_map(fn ($r) => is_string($r) ? trim($r) : '', is_array($liste) ? $liste : []),
            fn ($r) => $r !== '',
        )));
        $benutzer = fn ($liste) => array_values(array_unique(array_filter(
            array_map(fn ($u) => is_numeric($u) ? (int) $u : 0, is_array($liste) ? $liste : []),
            fn ($u) => $u > 0,
        )));

        return [
            'rollen_ein' => $rollen($roh['rollen_ein'] ?? []),
            'user_ein' => $benutzer($roh['user_ein'] ?? []),
            'rollen_aus' => $rollen($roh['rollen_aus'] ?? []),
            'user_aus' => $benutzer($roh['user_aus'] ?? []),
        ];
    }

    /**
     * Die Regeln auf eine Benutzer-Abfrage anwenden (WHERE-Gruppe):
     * (Rolle eingeschlossen ODER Kontakt eingeschlossen)
     * UND NICHT (Rolle ausgeschlossen ODER Kontakt ausgeschlossen).
     *
     * @param  Builder<User>  $q
     */
    public function anwenden(Builder $q): void
    {
        $r = self::regelnBereinigen($this->regeln ?? []);

        if ($r['rollen_ein'] === [] && $r['user_ein'] === []) {
            $q->whereRaw('1 = 0');

            return;
        }

        $q->where(function (Builder $ein) use ($r): void {
            if ($r['rollen_ein'] !== []) {
                $ein->orWhereHas('roles', fn (Builder $x) => $x->whereIn('roles.role_id', $r['rollen_ein']));
            }
            if ($r['user_ein'] !== []) {
                $ein->orWhereIn('users.id', $r['user_ein']);
            }
        });

        if ($r['rollen_aus'] !== []) {
            $q->whereDoesntHave('roles', fn (Builder $x) => $x->whereIn('roles.role_id', $r['rollen_aus']));
        }
        if ($r['user_aus'] !== []) {
            $q->whereNotIn('users.id', $r['user_aus']);
        }
    }

    /**
     * Die Gruppe fürs Formular/Modal: Regeln samt Namen der Kontakte, damit
     * die Chips im Modal ohne weitere Abfrage stehen.
     *
     * @return array<string, mixed>
     */
    public function fuerFormular(): array
    {
        $r = self::regelnBereinigen($this->regeln ?? []);
        $ids = array_merge($r['user_ein'], $r['user_aus']);
        $namen = $ids === []
            ? collect()
            : User::whereIn('id', $ids)->get(['id', 'name', 'email'])->keyBy('id');

        $kontakte = fn (array $liste) => array_values(array_map(
            fn (int $id) => ['id' => $id, 'name' => (string) ($namen[$id]->name ?? "#{$id}"), 'email' => (string) ($namen[$id]->email ?? '')],
            $liste,
        ));

        return [
            'id' => $this->id,
            'kennung' => $this->kennung(),
            'name' => $this->name,
            'rollen_ein' => $r['rollen_ein'],
            'rollen_aus' => $r['rollen_aus'],
            'user_ein' => $kontakte($r['user_ein']),
            'user_aus' => $kontakte($r['user_aus']),
        ];
    }
}
