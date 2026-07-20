<?php

namespace Intranet\Modules\Newsletter;

use App\Modules\Support\ModuleManifest;
use App\Modules\Support\ModuleServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Intranet\Modules\Newsletter\Console\NewsletterVersenden;

/**
 * Anmelde-Klasse des Newsletter-Moduls.
 *
 * Zwei Dinge passieren hier über das Übliche hinaus:
 *  1. Das Modul meldet einen EIGENEN Mail-Rahmen an (`_rahmen_newsletter`) und
 *     die Vorlage `newsletter`, die darin liegt. Beide sind danach ganz normal
 *     unter Verwaltung → Mailvorlagen bearbeitbar.
 *  2. Der Versand-Command wird beim Scheduler angemeldet.
 */
class NewsletterServiceProvider extends ModuleServiceProvider
{
    /** Schlüssel des Newsletter-Rahmens (ein zweiter Rahmen neben dem des Core). */
    public const RAHMEN = '_rahmen_newsletter';

    /** Schlüssel der Vorlage, die jede Ausgabe umschließt. */
    public const VORLAGE = 'newsletter';

    public function manifest(): ModuleManifest
    {
        return ModuleManifest::make('newsletter', 'Newsletter', icon: 'envelope')
            ->item('index', 'Ausgaben', 'module.newsletter.index', icon: 'list')
            ->item('create', 'Neue Ausgabe', 'module.newsletter.create', icon: 'plus');
    }

    public function boot(): void
    {
        parent::boot();

        // Läuft in Web UND Konsole: im Web für die Bearbeitung unter
        // Verwaltung → Mailvorlagen, in der Konsole für den Versand.
        $this->vorlagenAnmelden();

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([NewsletterVersenden::class]);

        // Jede Minute nachsehen, ob eine freigegebene Ausgabe offene Empfänger
        // hat. Der Command liefert nur in den Ausgangskorb ein – das eigentliche
        // Tempo bestimmt dessen Stundenlimit.
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('newsletter:versenden')
                ->everyMinute()
                ->withoutOverlapping()
                ->runInBackground();
        });
    }

    /**
     * Rahmen und Vorlage im Core-Register anmelden.
     *
     * `booted()`, damit das Register sicher steht. Der `class_exists`-Wächter
     * hält das Modul mit einem älteren Core lauffähig, der das Vorlagen-System
     * noch nicht kennt – dann fehlt eben die Bearbeitbarkeit.
     */
    private function vorlagenAnmelden(): void
    {
        if (! class_exists(\App\Mail\Vorlagen\VorlagenRegister::class)) {
            return;
        }

        $this->app->booted(function (): void {
            $register = $this->app->make(\App\Mail\Vorlagen\VorlagenRegister::class);

            $register->registrieren(new \App\Mail\Vorlagen\VorlagenDefinition(
                schluessel: self::RAHMEN,
                titel: 'Rahmen: Newsletter',
                beschreibung: 'Kopf und Fuß rund um jede Newsletter-Ausgabe. Darf anders aussehen '
                    .'als der allgemeine Rahmen – ein Rundbrief ist keine Systemmail.',
                platzhalter: [
                    'inhalt' => 'Die Ausgabe selbst (nicht selbst eintippen)',
                    'titel' => 'Haupttitel aus den Einstellungen',
                    'logo' => 'Logo aus den Einstellungen (leer, wenn keins hinterlegt ist)',
                    'jahr' => 'Aktuelles Jahr',
                ],
                betreff: null,
                html: self::RAHMEN_HTML,
                text: self::RAHMEN_TEXT,
                rahmen: null, // ist selbst ein Rahmen
                beispiele: [
                    'inhalt' => '<p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#374151;">'
                        .'Hier stehen die Bausteine der jeweiligen Ausgabe.</p>',
                ],
            ));

            $register->registrieren(new \App\Mail\Vorlagen\VorlagenDefinition(
                schluessel: self::VORLAGE,
                titel: 'Newsletter-Ausgabe',
                beschreibung: 'Was um JEDE Ausgabe herum steht – Anrede, Abbinder. Der eigentliche '
                    .'Text wird je Ausgabe im Newsletter-Modul zusammengestellt und über '
                    .'{{ inhalt }} eingesetzt.',
                platzhalter: [
                    'name' => 'Name des Empfängers',
                    'betreff' => 'Betreff, der bei der Ausgabe eingegeben wurde',
                    'ausgabe' => 'Interner Titel der Ausgabe (z. B. „Elternbrief Juli")',
                    'inhalt' => 'Die Bausteine der Ausgabe (nicht selbst eintippen)',
                ],
                // Der Betreff wird durchgereicht. Wer will, macht daraus
                // „[Schule] {{ betreff }}" – dann tragen alle Ausgaben das Präfix.
                betreff: '{{ betreff }}',
                html: self::AUSGABE_HTML,
                text: self::AUSGABE_TEXT,
                rahmen: self::RAHMEN,
                beispiele: [
                    'betreff' => 'Neues aus der Schule',
                    'ausgabe' => 'Elternbrief Juli',
                    'inhalt' => '<p style="margin:0 0 12px;font-size:17px;font-weight:bold;color:#1f2937;">Sommerfest</p>'
                        .'<p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#374151;">'
                        .'Am Samstag feiern wir auf dem Schulhof. Kuchenspenden gerne im Sekretariat anmelden.</p>',
                ],
            ));
        });
    }

    // ── Standardtexte ────────────────────────────────────────────────────────
    // Wie im Core: schlichtes, tabellenbasiertes HTML mit Inline-Styles. Nur so
    // sieht es in Outlook, Gmail & Co. verlässlich gleich aus.
    //
    // Gegenüber dem Core-Rahmen etwas breiter (640 statt 560) und mit einem
    // farbigen Streifen unter dem Kopf: Ein Rundbrief darf sich von einer
    // Passwort-Mail unterscheiden.

    private const RAHMEN_HTML = <<<'HTML'
<!DOCTYPE html>
<html lang="de">
<body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:24px 0;">
    <tr><td align="center">
      <table role="presentation" width="640" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e5e7eb;">
        <tr><td style="background:#ffffff;padding:20px 32px;">
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
            <tr>
              <td align="left" valign="middle" style="color:#1f2937;font-size:20px;font-weight:bold;">{{ titel }}</td>
              <td align="right" valign="middle">{{ logo }}</td>
            </tr>
          </table>
        </td></tr>
        <tr><td style="height:4px;background:#4f46e5;font-size:0;line-height:0;">&nbsp;</td></tr>
        <tr><td style="padding:32px;">
          {{ inhalt }}
        </td></tr>
        <tr><td style="padding:20px 32px;background:#f9fafb;border-top:1px solid #e5e7eb;">
          <span style="color:#9ca3af;font-size:12px;">
            Du bekommst diese Nachricht, weil du im Intranet von {{ titel }} eingetragen bist.<br>
            © {{ jahr }} {{ titel }}
          </span>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;

    private const RAHMEN_TEXT = <<<'TEXT'
{{ titel }}
========================================

{{ inhalt }}

—
Du bekommst diese Nachricht, weil du im Intranet von {{ titel }} eingetragen bist.
© {{ jahr }} {{ titel }}
TEXT;

    private const AUSGABE_HTML = <<<'HTML'
<p style="margin:0 0 20px;font-size:16px;">Hallo {{ name }}!</p>
{{ inhalt }}
HTML;

    private const AUSGABE_TEXT = <<<'TEXT'
Hallo {{ name }}!

{{ inhalt }}
TEXT;
}
