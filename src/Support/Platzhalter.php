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
     * @param  array<string, string>  $werte
     */
    public static function ersetzen(string $inhalt, array $werte): string
    {
        if ($inhalt === '') {
            return '';
        }

        return preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/',
            fn (array $treffer) => array_key_exists($treffer[1], $werte)
                ? (string) $werte[$treffer[1]]
                : $treffer[0],
            $inhalt,
        ) ?? $inhalt;
    }
}
