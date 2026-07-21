<?php

namespace Intranet\Modules\Newsletter\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Intranet\Modules\Newsletter\Support\Bausteine;
use Intranet\Modules\Newsletter\Support\Empfaengerkreis;

/**
 * Eine Newsletter-Ausgabe.
 *
 * Lebenslauf: `entwurf` (beliebig oft änderbar) → `versand` (Empfängerliste
 * steht fest, der Command arbeitet sie ab) → `versendet`.
 *
 * Ab der Freigabe ist die Ausgabe unveränderlich. Sonst bekäme die eine Hälfte
 * der Empfänger einen anderen Text als die andere – bei einem Versand, der sich
 * über Stunden zieht, ist das kein theoretischer Fall.
 */
class Kampagne extends Model
{
    public const ENTWURF = 'entwurf';

    public const VERSAND = 'versand';

    public const VERSENDET = 'versendet';

    /** Zusammengeklickt aus Bausteinen. */
    public const MODUS_BAUSTEINE = 'bausteine';

    /** Selbst geschriebenes HTML samt eigener Textfassung. */
    public const MODUS_CODE = 'code';

    protected $table = 'newsletter_kampagnen';

    protected $fillable = ['titel', 'betreff', 'modus', 'bausteine', 'html', 'text', 'zielgruppen', 'erstellt_von'];

    protected $casts = [
        'bausteine' => 'array',
        'zielgruppen' => 'array',
        'freigegeben_am' => 'datetime',
        'versand_beendet_am' => 'datetime',
    ];

    public function empfaenger(): HasMany
    {
        return $this->hasMany(Empfaenger::class, 'kampagne_id');
    }

    public function ersteller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'erstellt_von');
    }

    public function istEntwurf(): bool
    {
        return $this->status === self::ENTWURF;
    }

    public function imCodeModus(): bool
    {
        return $this->modus === self::MODUS_CODE;
    }

    /**
     * Der Inhalt der Ausgabe als Mail-HTML.
     *
     * Im Code-Modus das selbst geschriebene HTML, sonst die gerenderten
     * Bausteine. Beide Fassungen bleiben in der Zeile stehen – `modus`
     * entscheidet nur, welche gilt.
     */
    public function alsHtml(): string
    {
        return $this->imCodeModus()
            ? (string) $this->html
            : Bausteine::alsHtml($this->bausteine ?? []);
    }

    public function alsText(): string
    {
        return $this->imCodeModus()
            ? (string) $this->text
            : Bausteine::alsText($this->bausteine ?? []);
    }

    /** Hat die Ausgabe überhaupt etwas zu verschicken? */
    public function hatInhalt(): bool
    {
        return trim($this->alsHtml()) !== '';
    }

    /**
     * Die Ausgabe freigeben: Empfängerliste festschreiben und den Versand
     * anstoßen.
     *
     * Hier wird noch NICHTS verschickt – es entstehen nur die Zeilen, die der
     * Command danach abarbeitet. Bei 900 Empfängern würde ein Versand im
     * laufenden Aufruf ins Zeitlimit rennen; außerdem wäre ein Abbruch mittendrin
     * nicht wiederaufnehmbar.
     *
     * @return int Anzahl vorgemerkter Empfänger (0 = nichts passiert)
     */
    public function freigeben(User $von): int
    {
        if (! $this->istEntwurf()) {
            return 0;
        }

        $empfaenger = Empfaengerkreis::erreichbare($this->zielgruppen ?? []);

        if ($empfaenger->isEmpty()) {
            return 0;
        }

        $jetzt = now();

        $zeilen = $empfaenger->map(fn (User $u) => [
            'kampagne_id' => $this->id,
            'user_id' => $u->id,
            'email' => $u->email,
            'status' => Empfaenger::WARTEND,
            'created_at' => $jetzt,
            'updated_at' => $jetzt,
        ])->all();

        foreach (array_chunk($zeilen, 500) as $teil) {
            Empfaenger::insertOrIgnore($teil);
        }

        $this->forceFill([
            'status' => self::VERSAND,
            'freigegeben_am' => $jetzt,
            'freigegeben_von' => $von->id,
        ])->save();

        return count($zeilen);
    }

    /**
     * @return array{gesamt: int, eingeliefert: int, wartend: int, uebersprungen: int, fehler: int}
     */
    public function fortschritt(): array
    {
        $nach = $this->empfaenger()
            ->selectRaw('status, count(*) as anzahl')
            ->groupBy('status')
            ->pluck('anzahl', 'status');

        return [
            'gesamt' => (int) $nach->sum(),
            'eingeliefert' => (int) $nach->get(Empfaenger::EINGELIEFERT, 0),
            'wartend' => (int) $nach->get(Empfaenger::WARTEND, 0),
            'uebersprungen' => (int) $nach->get(Empfaenger::UEBERSPRUNGEN, 0),
            'fehler' => (int) $nach->get(Empfaenger::FEHLER, 0),
        ];
    }
}
