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
        ?string $absenderName = null,
        ?string $antwortAn = null,
    ): void {
        // Beide Fassungen (mit und ohne Rahmen) laufen über dieselbe Stelle:
        // erst fertig rendern, dann EINE Mail bauen – so bekommen beide denselben
        // Absender, Antwort-an, Auslöser und die Referenz fürs Maillog.
        $fertig = self::rendern($mailer, $mitRahmen, $betreff, $html, $text, $werte);

        Mail::html($fertig['html'], function ($nachricht) use ($an, $fertig, $referenz, $absenderName, $antwortAn) {
            $nachricht->to($an)->subject($fertig['betreff'])->text($fertig['text']);
            self::absenderSetzen($nachricht, $absenderName, $antwortAn);
            VorlagenMailer::quelleMarkieren($nachricht, self::QUELLE, $referenz);
        });
    }

    /**
     * Eigener Absender je Ausgabe: nur der angezeigte NAME wird ersetzt – die
     * Adresse bleibt die der Instanz (eine fremde Absenderadresse landet im
     * Spam). Antwort-an lenkt Rückfragen z. B. ins Postfach der Redaktion.
     * Beides leer = so, wie die Instanz es vorgibt. Eine Absender-Zeile in der
     * Verwaltung (Maillog → Absender, Modul Core / Auslöser Newsletter) gewinnt
     * beim Einliefern trotzdem – das ist dort so gewollt.
     */
    public static function absenderSetzen(\Illuminate\Mail\Message $nachricht, ?string $absenderName, ?string $antwortAn): void
    {
        if (filled($absenderName)) {
            $nachricht->from((string) config('mail.from.address'), $absenderName);
        }

        if (filled($antwortAn)) {
            $nachricht->replyTo($antwortAn);
        }
    }
}
