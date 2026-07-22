<?php

namespace Intranet\Modules\Newsletter\Support;

use App\Mail\Vorlagen\VorlagenMailer;
use Illuminate\Support\Facades\Mail;
use Intranet\Modules\Newsletter\NewsletterServiceProvider;

/**
 * Eine Ausgabe zustellen bzw. für die Vorschau rendern – in beiden Spielarten:
 *
 *  - MIT Rahmen: Der Inhalt wird als `{{ inhalt }}` in die Vorlage `newsletter`
 *    und deren Rahmen (Kopf, Fuß, Anrede) gelegt. Der Normalfall.
 *  - OHNE Rahmen: Das HTML IST die komplette Mail. Kein Kopf, kein Fuß, keine
 *    Anrede – der Verfasser liefert alles selbst. Für wer genau weiß, was er
 *    tut (fertige Kampagnen-Templates aus einem anderen Werkzeug).
 *
 * Platzhalter (`{{ name }}` …) werden in beiden Spielarten je Empfänger gefüllt
 * (siehe {@see Platzhalter}); im Maillog erscheint beides Mal „Newsletter".
 *
 * Command, Testmail und Vorschau laufen alle hier zusammen, damit die zwei
 * Spielarten an genau einer Stelle entschieden werden.
 */
class Zusteller
{
    /** Name, unter dem eine Newsletter-Mail im Maillog des Core auftaucht. */
    public const QUELLE = 'Newsletter';

    /**
     * Für die Vorschau: die fertige Mail rendern, ohne sie zu verschicken.
     *
     * @param  array<string, string>  $werte
     * @return array{betreff: string, html: string, text: string}
     */
    public static function rendern(
        VorlagenMailer $mailer,
        bool $mitRahmen,
        string $betreff,
        string $html,
        string $text,
        array $werte,
    ): array {
        $werte = self::werteMitBetreff($betreff, $werte);

        if ($mitRahmen) {
            return $mailer->rendern(
                NewsletterServiceProvider::VORLAGE,
                $werte + ['inhalt' => Platzhalter::ersetzen($html, $werte)],
                ['inhalt' => Platzhalter::ersetzen($text, $werte)],
            );
        }

        // Ohne Rahmen: das eingegebene HTML ist die ganze Mail.
        return [
            'betreff' => $werte['betreff'],
            'html' => Platzhalter::ersetzen($html, $werte),
            'text' => Platzhalter::ersetzen($text, $werte),
        ];
    }

    /**
     * Den Betreff selbst durch die Platzhalter jagen und das Ergebnis nach
     * `werte['betreff']` schreiben.
     *
     * Nötig, weil im gerahmten Fall der Betreff über die Vorlage `newsletter`
     * läuft (`betreff: {{ betreff }}`) und der Core nur EINEN Durchgang macht:
     * Ein `{{ ausgabe }}`, das jemand in den Betreff schreibt, bliebe sonst
     * stehen. So ist `werte['betreff']` überall schon fertig aufgelöst.
     *
     * @param  array<string, string>  $werte
     * @return array<string, string>
     */
    private static function werteMitBetreff(string $betreff, array $werte): array
    {
        $werte['betreff'] = Platzhalter::ersetzen($betreff, $werte);

        return $werte;
    }

    /**
     * Eine Mail in den Ausgangskorb einliefern (echter Versand).
     *
     * @param  array<string, string>  $werte
     * @param  string|null  $referenz  Freie Referenz, mit der die Ausgaben-Seite
     *                                 diese Mail später im Maillog wiederfindet
     *                                 (`newsletter:<ausgabe>:<empfänger>`).
     */
    public static function zustellen(
        VorlagenMailer $mailer,
        string $an,
        bool $mitRahmen,
        string $betreff,
        string $html,
        string $text,
        array $werte,
        ?string $referenz = null,
    ): void {
        $werte = self::werteMitBetreff($betreff, $werte);

        if ($mitRahmen) {
            $mailer->senden(
                NewsletterServiceProvider::VORLAGE,
                $an,
                $werte + ['inhalt' => Platzhalter::ersetzen($html, $werte)],
                ['inhalt' => Platzhalter::ersetzen($text, $werte)],
                self::QUELLE,
                $referenz,
            );

            return;
        }

        // Ohne Rahmen: direkt verschicken, ohne Vorlage. Auslöser UND Referenz
        // trotzdem markieren, damit auch diese Mails im Maillog als „Newsletter"
        // stehen und der Ausgabe zugeordnet werden können.
        $fertigHtml = Platzhalter::ersetzen($html, $werte);
        $fertigText = Platzhalter::ersetzen($text, $werte);
        $fertigBetreff = $werte['betreff'];

        Mail::html($fertigHtml, function ($nachricht) use ($an, $fertigBetreff, $fertigText, $referenz) {
            $nachricht->to($an)->subject($fertigBetreff)->text($fertigText);
            VorlagenMailer::quelleMarkieren($nachricht, self::QUELLE, $referenz);
        });
    }
}
