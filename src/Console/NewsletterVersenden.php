<?php

namespace Intranet\Modules\Newsletter\Console;

use App\Mail\Vorlagen\VorlagenMailer;
use App\Support\Zustellbarkeit;
use Illuminate\Console\Command;
use Intranet\Modules\Newsletter\Models\Empfaenger;
use Intranet\Modules\Newsletter\Models\Kampagne;
use Intranet\Modules\Newsletter\NewsletterServiceProvider;
use Throwable;

/**
 * Liefert freigegebene Ausgaben in den Ausgangskorb des Core ein.
 *
 * Zwei Drosseln hintereinander, mit verschiedenen Aufgaben:
 *  - Diese hier (`--anzahl`) begrenzt, wie viele Zeilen ein einzelner Lauf
 *    schreibt. Sie schützt den Lauf selbst vor dem Zeitlimit.
 *  - Der Ausgangskorb begrenzt, wie viele Mails je Stunde den Server verlassen.
 *    Sie schützt den Ruf der Absenderadresse.
 *
 * Deshalb darf hier großzügig eingeliefert werden: Was hier landet, geht
 * dadurch noch lange nicht sofort raus.
 */
class NewsletterVersenden extends Command
{
    protected $signature = 'newsletter:versenden
                            {--anzahl=200 : Wie viele Empfänger dieser Lauf höchstens einliefert}';

    protected $description = 'Freigegebene Newsletter-Ausgaben in den Ausgangskorb einliefern';

    public function handle(VorlagenMailer $mailer): int
    {
        $rest = max(1, (int) $this->option('anzahl'));

        $kampagnen = Kampagne::query()
            ->where('status', Kampagne::VERSAND)
            ->orderBy('id') // Ältere Ausgabe zuerst fertig machen
            ->get();

        foreach ($kampagnen as $kampagne) {
            if ($rest <= 0) {
                break;
            }

            $rest -= $this->abarbeiten($kampagne, $mailer, $rest);
        }

        return self::SUCCESS;
    }

    /** @return int Anzahl bearbeiteter Empfänger */
    private function abarbeiten(Kampagne $kampagne, VorlagenMailer $mailer, int $hoechstens): int
    {
        // Einmal je Ausgabe rendern, nicht je Empfänger: Der Inhalt ist für
        // alle gleich, nur die Anrede unterscheidet sich.
        $html = $kampagne->alsHtml();
        $text = $kampagne->alsText();

        $offen = $kampagne->empfaenger()
            ->wartend()
            ->with('user')
            ->orderBy('id')
            ->limit($hoechstens)
            ->get();

        foreach ($offen as $empfaenger) {
            $this->einliefern($kampagne, $empfaenger, $mailer, $html, $text);
        }

        // Fertig? Erst prüfen, nachdem dieser Lauf gearbeitet hat.
        if ($kampagne->empfaenger()->wartend()->doesntExist()) {
            $kampagne->forceFill([
                'status' => Kampagne::VERSENDET,
                'versand_beendet_am' => now(),
            ])->save();

            $this->info("Ausgabe „{$kampagne->titel}\" ist vollständig eingeliefert.");
        }

        return $offen->count();
    }

    private function einliefern(
        Kampagne $kampagne,
        Empfaenger $empfaenger,
        VorlagenMailer $mailer,
        string $html,
        string $text,
    ): void {
        // Zwischen Freigabe und Versand können Stunden liegen. In der Zeit kann
        // jemand gesperrt worden sein oder eine neue Adresse bekommen haben –
        // deshalb hier noch einmal prüfen statt der Liste von damals zu trauen.
        $benutzer = $empfaenger->user;

        if ($benutzer?->istGesperrt()) {
            $empfaenger->abschliessen(Empfaenger::UEBERSPRUNGEN, 'Benutzer ist gesperrt');

            return;
        }

        $adresse = (string) ($benutzer->email ?? $empfaenger->email);

        if (! Zustellbarkeit::zustellbar($adresse)) {
            $empfaenger->abschliessen(Empfaenger::UEBERSPRUNGEN, 'Adresse ist nicht zustellbar');

            return;
        }

        try {
            $mailer->senden(
                NewsletterServiceProvider::VORLAGE,
                $adresse,
                [
                    'name' => (string) ($benutzer->name ?? ''),
                    'betreff' => $kampagne->betreff,
                    'ausgabe' => $kampagne->titel,
                    'inhalt' => $html,
                ],
                // In der Textfassung hat das HTML der Bausteine nichts verloren.
                ['inhalt' => $text],
            );

            $empfaenger->abschliessen(Empfaenger::EINGELIEFERT);
        } catch (Throwable $e) {
            // Ein einzelner Fehlschlag darf den Rest der Ausgabe nicht aufhalten.
            $empfaenger->abschliessen(Empfaenger::FEHLER, mb_substr($e->getMessage(), 0, 250));

            $this->warn("Empfänger {$empfaenger->id}: {$e->getMessage()}");
        }
    }
}
