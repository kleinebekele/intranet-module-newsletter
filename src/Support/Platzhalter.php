<?php

namespace Intranet\Modules\Newsletter\Support;

/**
 * Platzhalter im Inhalt einer Ausgabe ersetzen (`{{ name }}` & Co.).
 *
 * Warum das hier nochmal passiert, obwohl der Core das doch kann:
 * {@see \App\Mail\Vorlagen\VorlagenMailer} ersetzt Platzhalter in der VORLAGE –
 * in einem einzigen Durchgang. Was dabei eingesetzt wird (bei uns: die ganze
 * Ausgabe als `{{ inhalt }}`), wird nicht noch einmal durchsucht. Ohne diese
 * Klasse stünde ein `{{ name }}`, das jemand in seinen eigenen Code schreibt,
 * wörtlich in der Mail.
 *
 * Bewusst dieselbe Schreibweise und dasselbe Verhalten wie im Core: `{{ name }}`
 * und `{{name}}` gelten beide, Unbekanntes bleibt stehen (dann fällt beim Testen
 * auf, dass ein Wert fehlt), und es wird per Textersetzung gearbeitet – nicht
 * über Blade, damit aus dem Backend kein Code ausgeführt werden kann.
 */
class Platzhalter
{
    /** @var array<string, string> name => Erklärung, für die Oberfläche */
    public const VERFUEGBAR = [
        'name' => 'Name des Empfängers',
        'ausgabe' => 'Interner Titel der Ausgabe',
        'betreff' => 'Betreff dieser Ausgabe',
    ];

    /**
     * Werte, die schon fertiges HTML SIND und deshalb roh eingesetzt werden:
     * die Bausteine (`inhalt`) und das Logo-Tag des Core. Alles andere ist
     * eingetippter Text (Name, Titel, Betreff) und wird im HTML maskiert.
     */
    public const ROH = ['inhalt', 'logo'];

    /**
     * @param  array<string, string>  $werte
     * @param  bool  $html  true = Ziel ist HTML: Textwerte maskieren (siehe {@see fuerHtml()})
     */
    public static function ersetzen(string $inhalt, array $werte, bool $html = false): string
    {
        if ($inhalt === '') {
            return '';
        }

        if ($html) {
            $werte = self::fuerHtml($werte);
        }

        return preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/',
            fn (array $treffer) => array_key_exists($treffer[1], $werte)
                ? (string) $werte[$treffer[1]]
                : $treffer[0],
            $inhalt,
        ) ?? $inhalt;
    }

    /**
     * Die Werte fürs HTML: Textwerte mit e() maskiert, {@see ROH} unverändert.
     * Ein Benutzername wie `<img onerror=…>` steht so als Text in der Mail.
     *
     * @param  array<string, string>  $werte
     * @return array<string, string>
     */
    public static function fuerHtml(array $werte): array
    {
        foreach ($werte as $name => $wert) {
            if (! in_array($name, self::ROH, true)) {
                $werte[$name] = e((string) $wert);
            }
        }

        return $werte;
    }
}
