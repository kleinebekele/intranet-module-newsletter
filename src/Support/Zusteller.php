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
     * Registerschlüssel, unter dem eine eigene Vorlage des Moduls für einen
     * Render-Aufruf als Rahmen angemeldet wird (siehe {@see imEigenenRahmen()}).
     */
    public const EIGENER_RAHMEN = '_rahmen_newsletter_eigen';

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
        ?object $vorlage = null,
    ): array {
        $werte = self::werteMitBetreff($betreff, $werte);

        if ($mitRahmen) {
            $htmlWerte = $werte + ['inhalt' => Platzhalter::ersetzen($html, $werte)];
            $textWerte = ['inhalt' => Platzhalter::ersetzen($text, $werte)];

            if ($vorlage !== null) {
                return self::imEigenenRahmen($mailer, $vorlage, $htmlWerte, $textWerte);
            }

            return $mailer->rendern(NewsletterServiceProvider::VORLAGE, $htmlWerte, $textWerte);
        }

        // Ohne Rahmen: das eingegebene HTML ist die ganze Mail.
        return [
            'betreff' => $werte['betreff'],
            'html' => Platzhalter::ersetzen($html, $werte),
            'text' => Platzhalter::ersetzen($text, $werte),
        ];
    }

    /**
     * Die Ausgabe in einer EIGENEN Vorlage des Moduls (Menüpunkt „Mailvorlagen")
     * rendern statt im Rahmen aus der Verwaltung.
     *
     * Der Core kennt Rahmen nur über sein Register. Deshalb wird die gewählte
     * Vorlage für diesen Aufruf als Rahmen dort angemeldet und die Vorlage
     * `newsletter` (Anrede, Abbinder – weiterhin aus der Verwaltung) auf ihn
     * umgebogen. Eine gespeicherte Fassung von `newsletter` bleibt wirksam.
     *
     * @param  object  $vorlage  Braucht `html` und `text` (Modell {@see \Intranet\Modules\Newsletter\Models\Vorlage}
     *                           oder eine ungespeicherte Attrappe für die Vorschau).
     * @param  array<string, string>  $htmlWerte
     * @param  array<string, string>  $textWerte
     * @return array{betreff: string, html: string, text: string}
     */
    private static function imEigenenRahmen(VorlagenMailer $mailer, object $vorlage, array $htmlWerte, array $textWerte): array
    {
        $register = app(\App\Mail\Vorlagen\VorlagenRegister::class);
        $basis = $register->finden(NewsletterServiceProvider::VORLAGE);

        if ($basis === null) {
            // Älterer Core ohne Newsletter-Vorlage – dann gibt es auch keinen
            // Rahmen, in den sich etwas legen ließe.
            return $mailer->rendern(NewsletterServiceProvider::VORLAGE, $htmlWerte, $textWerte);
        }

        // Ohne eigene Textfassung gilt die des Rahmens aus der Verwaltung –
        // eine leere Textspur wäre für Mailprogramme ohne HTML ein Rückschritt.
        $text = trim((string) ($vorlage->text ?? ''));

        $register->registrieren(new \App\Mail\Vorlagen\VorlagenDefinition(
            schluessel: self::EIGENER_RAHMEN,
            titel: 'Rahmen: eigene Newsletter-Vorlage',
            beschreibung: 'Zur Laufzeit aus dem Newsletter-Modul angemeldet.',
            platzhalter: [],
            betreff: null,
            html: (string) $vorlage->html,
            text: $text !== '' ? $text : self::standardRahmen()['text'],
            rahmen: null,
            modul: self::QUELLE,
        ));

        $definition = clone $basis;
        $definition->rahmen = self::EIGENER_RAHMEN;

        return $mailer->rendernMit(
            \App\Models\MailVorlage::find(NewsletterServiceProvider::VORLAGE),
            $definition,
            $htmlWerte,
            $textWerte,
        );
    }

    /**
     * Der Newsletter-Rahmen aus Verwaltung → Mailvorlagen, so wie er gerade
     * gilt (angepasste Fassung, sonst Standard). Vorbelegung für eine neue
     * eigene Vorlage und Textspur-Rückfall.
     *
     * @return array{html: string, text: string}
     */
    public static function standardRahmen(): array
    {
        $definition = app(\App\Mail\Vorlagen\VorlagenRegister::class)->finden(NewsletterServiceProvider::RAHMEN);
        $gespeichert = \App\Models\MailVorlage::find(NewsletterServiceProvider::RAHMEN);

        return [
            'html' => (string) ($gespeichert->html ?? $definition?->html ?? ''),
            'text' => (string) ($gespeichert->text ?? $definition?->text ?? ''),
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
        ?object $konto = null,
        ?object $vorlage = null,
    ): void {
        // Beide Fassungen (mit und ohne Rahmen) laufen über dieselbe Stelle:
        // erst fertig rendern, dann EINE Mail bauen – so bekommen beide denselben
        // Absender, Antwort-an, Auslöser und die Referenz fürs Maillog.
        $fertig = self::rendern($mailer, $mitRahmen, $betreff, $html, $text, $werte, $vorlage);

        Mail::html($fertig['html'], function ($nachricht) use ($an, $fertig, $referenz, $absenderName, $antwortAn, $konto) {
            $nachricht->to($an)->subject($fertig['betreff'])->text($fertig['text']);
            self::absenderSetzen($nachricht, $absenderName, $antwortAn, $konto);
            VorlagenMailer::quelleMarkieren($nachricht, self::QUELLE, $referenz);
        });
    }

    /**
     * Die wählbaren SMTP-Absender aus der Core-Verwaltung (Maillog → SMTP-Absender),
     * nur aktive. Leer, wenn der Core sie noch nicht kennt oder keins angelegt ist.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    public static function konten(): \Illuminate\Support\Collection
    {
        if (! class_exists(\App\Models\MailKonto::class) || ! \Illuminate\Support\Facades\Schema::hasTable('mail_konten')) {
            return collect();
        }

        return \App\Models\MailKonto::where('aktiv', true)->orderBy('bezeichnung')->get();
    }

    /**
     * Eigener Absender je Ausgabe: nur der angezeigte NAME wird ersetzt – die
     * Adresse bleibt die der Instanz (eine fremde Absenderadresse landet im
     * Spam). Antwort-an lenkt Rückfragen z. B. ins Postfach der Redaktion.
     * Beides leer = so, wie die Instanz es vorgibt. Eine Absender-Zeile in der
     * Verwaltung (Maillog → Absender, Modul Core / Auslöser Newsletter) gewinnt
     * beim Einliefern trotzdem – das ist dort so gewollt.
     */
    public static function absenderSetzen(\Illuminate\Mail\Message $nachricht, ?string $absenderName, ?string $antwortAn, ?object $konto = null): void
    {
        // Mit SMTP-Absender: dessen Adresse und Zugang, Name/Antwort-an der
        // Ausgabe gewinnen über die Vorgaben des Kontos.
        if ($konto !== null && method_exists($konto, 'anMail')) {
            $konto->anMail($nachricht, $absenderName, $antwortAn);

            return;
        }

        if (filled($absenderName)) {
            $nachricht->from((string) config('mail.from.address'), $absenderName);
        }

        if (filled($antwortAn)) {
            $nachricht->replyTo($antwortAn);
        }
    }
}
