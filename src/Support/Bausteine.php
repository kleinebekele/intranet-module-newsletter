<?php

namespace Intranet\Modules\Newsletter\Support;

/**
 * Die Bausteine einer Newsletter-Ausgabe – und wie daraus Mail-HTML und
 * Klartext werden.
 *
 * Warum Bausteine und kein HTML-Feld: Wer den Newsletter schreibt, ist in der
 * Regel nicht der Entwickler. Ein vergessenes </table> zerlegt das Layout in
 * Outlook, ohne dass es beim Schreiben auffällt. Hier gibt es stattdessen eine
 * Handvoll fester Formen, aus denen diese Klasse verlässliches, tabellen- und
 * inline-style-basiertes Mail-HTML baut – genauso wie es der Core für seine
 * eigenen Vorlagen von Hand tut.
 *
 * Eingetippter Text wird IMMER maskiert – mit einer bewussten Ausnahme: Der
 * Baustein „HTML-Code" übernimmt sein Feld roh. Er ersetzt den früheren
 * Reiter „Eigener Code" und ist für die, die wissen, was sie tun (fertige
 * Kampagnen-Templates, Sonderlayouts). Zusammen mit der Rahmen-Wahl „keine"
 * an der Ausgabe kann er die komplette Mail sein.
 */
class Bausteine
{
    /** @var array<string, string> typ => Klartext für die Oberfläche */
    public const TYPEN = [
        'ueberschrift' => 'Überschrift',
        'text' => 'Textabsatz',
        'bild' => 'Bild',
        'knopf' => 'Knopf',
        'trenner' => 'Trennlinie',
        'html' => 'HTML-Code',
    ];

    /**
     * Rohdaten aus dem Formular in eine saubere, speicherbare Form bringen.
     *
     * Unbekannte Typen und unbekannte Felder fallen weg, Bausteine ohne Inhalt
     * ebenfalls – ein leerer Absatz, den jemand versehentlich hinzugefügt hat,
     * soll keine Lücke in die Mail reißen.
     *
     * @param  array<int, mixed>  $roh
     * @return array<int, array<string, mixed>>
     */
    public static function bereinigen(array $roh): array
    {
        $sauber = [];

        foreach ($roh as $baustein) {
            if (! is_array($baustein)) {
                continue;
            }

            $typ = is_string($baustein['typ'] ?? null) ? $baustein['typ'] : '';

            if (! array_key_exists($typ, self::TYPEN)) {
                continue;
            }

            $fertig = match ($typ) {
                'ueberschrift' => [
                    'typ' => 'ueberschrift',
                    'text' => self::text($baustein['text'] ?? ''),
                    'gross' => (bool) ($baustein['gross'] ?? false),
                ],
                'text' => [
                    'typ' => 'text',
                    'text' => self::text($baustein['text'] ?? ''),
                ],
                'bild' => [
                    'typ' => 'bild',
                    'url' => self::adresse($baustein['url'] ?? '', self::SCHEMA_BILD),
                    'alt' => self::text($baustein['alt'] ?? ''),
                    'link' => self::adresse($baustein['link'] ?? '', self::SCHEMA_LINK),
                ],
                'knopf' => [
                    'typ' => 'knopf',
                    'text' => self::text($baustein['text'] ?? ''),
                    'url' => self::adresse($baustein['url'] ?? '', self::SCHEMA_LINK),
                ],
                'trenner' => ['typ' => 'trenner'],
                // Roh, nicht maskiert – siehe Klassenkommentar.
                'html' => [
                    'typ' => 'html',
                    'html' => is_scalar($baustein['html'] ?? null) ? trim((string) $baustein['html']) : '',
                ],
            };

            if (self::istLeer($fertig)) {
                continue;
            }

            $sauber[] = $fertig;
        }

        return $sauber;
    }

    /**
     * Welche eingegebenen Adressen würde {@see bereinigen()} verwerfen? Für
     * eine Fehlermeldung beim Speichern – sonst verschwände z. B. ein Knopf mit
     * `www.schule.de` (ohne https://) kommentarlos aus der Ausgabe.
     *
     * @param  array<int, mixed>  $roh
     * @return array<int, string>  je verworfener Adresse ein Satz für die Oberfläche
     */
    public static function verworfeneAdressen(array $roh): array
    {
        $felder = [
            'bild' => ['url' => [self::SCHEMA_BILD, 'Bild-Adresse'], 'link' => [self::SCHEMA_LINK, 'Bild-Link']],
            'knopf' => ['url' => [self::SCHEMA_LINK, 'Knopf-Ziel']],
        ];
        $meldungen = [];

        foreach (array_values($roh) as $nr => $baustein) {
            $typ = is_array($baustein) && is_string($baustein['typ'] ?? null) ? $baustein['typ'] : '';

            foreach ($felder[$typ] ?? [] as $feld => [$schema, $label]) {
                $wert = self::text($baustein[$feld] ?? '');
                if ($wert !== '' && self::adresse($wert, $schema) === '') {
                    $meldungen[] = sprintf('Baustein %d: %s „%s" beginnt nicht mit %s.', $nr + 1, $label, mb_strimwidth($wert, 0, 60, '…'),
                        $schema === self::SCHEMA_BILD ? 'https:// (oder ist ein hochgeladenes Bild)' : 'https:// oder mailto:');
                }
            }
        }

        return $meldungen;
    }

    /**
     * Die Bausteine als Mail-HTML.
     *
     * @param  array<int, array<string, mixed>>  $bausteine
     */
    public static function alsHtml(array $bausteine): string
    {
        $teile = [];

        foreach ($bausteine as $b) {
            $teile[] = match ($b['typ']) {
                'ueberschrift' => sprintf(
                    '<p style="margin:0 0 12px;font-size:%s;font-weight:bold;color:#1f2937;">%s</p>',
                    ($b['gross'] ?? false) ? '22px' : '17px',
                    e($b['text']),
                ),
                'text' => self::absaetze($b['text']),
                'bild' => self::bild($b),
                // Auch beim Rendern nochmals geprüft: Ausgaben von vor der
                // Schema-Prüfung tragen ihre Adressen ungeprüft in der DB.
                'knopf' => ($url = self::adresse($b['url'] ?? '', self::SCHEMA_LINK)) === '' ? '' : sprintf(
                    '<p style="margin:0 0 20px;"><a href="%s" style="display:inline-block;background:#4f46e5;color:#ffffff;'
                    .'text-decoration:none;padding:12px 24px;border-radius:8px;font-weight:bold;">%s</a></p>',
                    e($url),
                    e($b['text']),
                ),
                'trenner' => '<hr style="border:0;border-top:1px solid #e5e7eb;margin:24px 0;">',
                'html' => (string) ($b['html'] ?? ''),
                default => '',
            };
        }

        return implode("\n", array_filter($teile));
    }

    /**
     * Dieselben Bausteine als reiner Text – für Empfänger, deren Programm kein
     * HTML anzeigt, und für den Spamfilter, der beide Fassungen vergleicht.
     *
     * @param  array<int, array<string, mixed>>  $bausteine
     */
    public static function alsText(array $bausteine): string
    {
        $teile = [];

        foreach ($bausteine as $b) {
            $teile[] = match ($b['typ']) {
                // Unterstrichen statt fett: Im Klartext ist das die einzige
                // Möglichkeit, eine Überschrift als solche kenntlich zu machen.
                'ueberschrift' => $b['text']."\n".str_repeat('-', mb_strlen($b['text'])),
                'text' => $b['text'],
                'bild' => ($b['alt'] ?? '') !== '' ? '[Bild: '.$b['alt'].']' : '',
                'knopf' => trim(($b['text'] ?? '').': '.($b['url'] ?? ''), ': '),
                'trenner' => '—',
                // Aus dem rohen HTML eine lesbare Textspur: Tags weg, Entities
                // auflösen, Leerraum zusammenziehen. Das /u ist Pflicht: Ohne
                // trifft \s einzelne Bytes wie das A0 aus &nbsp; (C2 A0) und
                // hinterlässt kaputtes UTF-8 – die Vorschau scheitert dann mit 500.
                'html' => trim(preg_replace('/[ \t]*\R\s*/u', "\n", html_entity_decode(
                    strip_tags(preg_replace('~<(br|/p|/div|/tr|/h[1-6]|/li)[^>]*>~i', "\n", (string) ($b['html'] ?? ''))),
                    ENT_QUOTES | ENT_HTML5,
                    'UTF-8',
                )) ?? ''),
                default => '',
            };
        }

        return implode("\n\n", array_filter($teile, fn ($t) => trim((string) $t) !== ''));
    }

    /**
     * Ein Textabsatz: Leerzeilen trennen Absätze, einfache Zeilenumbrüche
     * bleiben Umbrüche. Adressen im Text werden anklickbar.
     */
    private static function absaetze(string $text): string
    {
        $absaetze = preg_split('/\R{2,}/u', trim($text)) ?: [];
        $html = [];

        foreach ($absaetze as $absatz) {
            if (trim($absatz) === '') {
                continue;
            }

            $sicher = nl2br(e($absatz), false);

            $html[] = '<p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#374151;">'
                .self::verlinken($sicher).'</p>';
        }

        return implode("\n", $html);
    }

    /**
     * http(s)-Adressen im bereits maskierten Text anklickbar machen.
     *
     * Läuft bewusst NACH dem Maskieren: Der Text enthält an dieser Stelle kein
     * Markup mehr, das die Erkennung durcheinanderbringen könnte.
     */
    private static function verlinken(string $maskiert): string
    {
        return preg_replace(
            '~(https?://[^\s<]+?)(?=[.,;:!?)]?(?:\s|<br|$))~iu',
            '<a href="$1" style="color:#4f46e5;">$1</a>',
            $maskiert,
        ) ?? $maskiert;
    }

    /** @param  array<string, mixed>  $b */
    private static function bild(array $b): string
    {
        $url = self::adresse($b['url'] ?? '', self::SCHEMA_BILD);
        if ($url === '') {
            return '';
        }

        $img = sprintf(
            '<img src="%s" alt="%s" style="display:block;max-width:100%%;height:auto;border:0;border-radius:8px;">',
            e($url),
            e($b['alt'] ?? ''),
        );

        $link = self::adresse($b['link'] ?? '', self::SCHEMA_LINK);
        if ($link !== '') {
            $img = sprintf('<a href="%s">%s</a>', e($link), $img);
        }

        return '<p style="margin:0 0 16px;">'.$img.'</p>';
    }

    /** Erlaubte Anfänge für Knopf- und Bild-Links. */
    private const SCHEMA_LINK = '~^(https?://|mailto:)~i';

    /** Erlaubte Anfänge für Bildquellen: Webadresse oder eingebettetes Bild. */
    private const SCHEMA_BILD = '~^(https?://|data:image/)~i';

    /**
     * Eine Adresse nur durchlassen, wenn sie mit einem erlaubten Schema beginnt –
     * sonst leer. Hält `javascript:` & Co. aus Knöpfen und Bildern fern (das
     * Maskieren allein hilft dagegen nicht, das Schema bleibt ja stehen).
     * Steuerzeichen fallen vorher auf: Browser ignorieren sie mitten im Schema.
     */
    private static function adresse(mixed $wert, string $schema): string
    {
        $url = self::text($wert);

        if ($url === '' || preg_match('/[\x00-\x1F\x7F]/', $url) || ! preg_match($schema, $url)) {
            return '';
        }

        return $url;
    }

    private static function text(mixed $wert): string
    {
        return is_scalar($wert) ? trim((string) $wert) : '';
    }

    /** @param  array<string, mixed>  $b */
    private static function istLeer(array $b): bool
    {
        return match ($b['typ']) {
            'ueberschrift', 'text' => $b['text'] === '',
            'bild' => $b['url'] === '',
            // Ein Knopf ohne Ziel ist eine Enttäuschung, kein Knopf.
            'knopf' => $b['text'] === '' || $b['url'] === '',
            'html' => $b['html'] === '',
            default => false,
        };
    }
}
