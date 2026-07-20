# Newsletter-Modul

Rundmails an ausgewählte Benutzerrollen – für die modulare
[Intranet-Plattform](https://github.com/kleinebekele/intranet-core).

Eine Ausgabe wird aus **Bausteinen** zusammengestellt (Überschrift, Text, Bild, Knopf,
Trennlinie), an eine oder mehrere **Rollen** adressiert und erst nach ausdrücklicher
**Freigabe** verschickt. Der Versand läuft über den Ausgangskorb des Core und ist damit
automatisch gedrosselt.

## Installation

```bash
composer require do1emu/module-newsletter
php artisan migrate
php artisan modules:sync
php artisan storage:link   # nur einmalig, falls noch nicht geschehen (Bild-Bausteine)
```

⚠️ **Der Scheduler muss laufen.** Ohne `* * * * * php artisan schedule:run` bleibt jede
Ausgabe im Status „Versand läuft" stehen – wie jede andere Mail des Intranets auch.

## Wie es arbeitet

Das Modul erfindet **keinen zweiten Mailweg**. Es schreibt in dieselbe `mail_outbox` wie
jede andere Mail. Daraus folgt ohne eigenes Zutun:

- **Drosselung** über das Stundenlimit (Einstellungen → Mailversand). Ein Newsletter an 900
  Personen rieselt bei Limit 250 über rund vier Stunden raus.
- **Vorfahrt für Wichtiges:** 2FA- und Passwort-Mails haben Priorität 10 und überholen den
  Newsletter (Priorität 0). Niemand wartet Stunden auf seinen Anmeldecode.
- **Künstliche Adressen** (`.intern`) fallen still raus – Benutzer ohne echte Mailadresse
  sprengen den Versand nicht.

Der Command `newsletter:versenden` läuft minütlich und liefert höchstens `--anzahl` (Standard
200) Empfänger je Lauf ein. Das schützt den einzelnen Lauf vor dem Zeitlimit; das Tempo nach
außen bestimmt weiterhin der Ausgangskorb.

## Zielgruppen

Auswählbar sind **alle Rollen, die es in dieser Instanz gibt** – das Modul bringt keine
eigenen mit und kennt keine fest verdrahteten Gruppen. Wer im Admin-Panel eine neue Rolle
anlegt, kann sie sofort anschreiben. Zusätzlich gibt es „Alle Benutzer".

Immer ausgeschlossen: gesperrte Konten und Adressen, an die nicht zugestellt werden kann.
Die Aufschlüsselung („erreicht 412 von 917") steht schon im Formular, **bevor** man freigibt.

## Vorlagen

Das Modul meldet zwei Einträge unter *Verwaltung → Mailvorlagen* an:

| Schlüssel | Was es ist |
|---|---|
| `_rahmen_newsletter` | Eigener Rahmen: Kopf, Logo, farbiger Streifen, Fußzeile. Getrennt vom Rahmen der Systemmails, weil ein Rundbrief anders aussehen darf. |
| `newsletter` | Was um **jede** Ausgabe steht: Anrede, Abbinder. Der Betreff wird über `{{ betreff }}` durchgereicht – wer will, macht daraus `[Schule] {{ betreff }}`. |

Beides ist im Backend bearbeitbar, hat Live-Vorschau, Testmail und „Standard wiederherstellen".

⚠️ Der eigene Rahmen braucht **Core ≥ `VorlagenDefinition` mit `$rahmen`-Feld**. Mit einem
älteren Core meldet das Modul seine Vorlagen nicht an und der Versand fällt auf den
allgemeinen Rahmen zurück.

## Wer darf schreiben?

Wer das Modul sehen darf, darf auch schreiben und freigeben. Für eine eigene Redaktion legt
man im Admin-Panel eine Rolle an und gibt das Modul nur ihr frei (Modulverwaltung →
Sichtbarkeit).

## Bekannte Grenzen

- **Keine Anhänge.** 900× dasselbe PDF in der Outbox-Tabelle wäre kein guter Tausch – die
  Outbox speichert jede Mail vollständig. Stattdessen: Datei als Bild-Baustein hochladen oder
  verlinken.
- **Keine Abmeldung (Opt-out).** Für eine interne Rundmail an Mitglieder vertretbar; ein
  Abmelde-Link bräuchte einen Token-Link ohne Anmeldung.
- **Kein zeitversetzter Versand.** Freigabe heißt: läuft ab jetzt.
