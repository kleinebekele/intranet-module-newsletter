# Newsletter-Modul

Rundmails an ausgewählte Benutzerrollen – für die modulare
[Intranet-Plattform](https://github.com/kleinebekele/intranet-core).

Eine Ausgabe wird an eine oder mehrere **Rollen** adressiert und erst nach ausdrücklicher
**Freigabe** verschickt. Der Versand läuft über den Ausgangskorb des Core und ist damit
automatisch gedrosselt.

## Der Baukasten

Eine Ausgabe besteht aus Bausteinen: Überschrift, Textabsatz, Bild, Knopf, Trennlinie – und
**HTML-Code**. Eingetippter Text wird immer maskiert; das Layout ist nicht zerschießbar. Nur der
Baustein *HTML-Code* übernimmt sein Feld roh (für fertige Kampagnen-Templates, Sonderlayouts);
seine Textspur entsteht per `strip_tags`.

Der frühere Reiter „Eigener Code" ist seit v1.9 weg – alte Ausgaben im Code-Modus wurden per
Migration in einen HTML-Baustein überführt.

**Rahmen je Ausgabe** (Dropdown *Mailvorlage* im Kopf):

- **Allgemeiner Rahmen des Intranets** – derselbe wie bei Systemmails.
- **Eine eigene Vorlage** aus dem Menüpunkt *Mailvorlagen* (siehe unten).
- **Keine – nur eigener Code** – die Bausteine *sind* die ganze Mail. Kein Rahmen, keine Anrede;
  der Verfasser liefert alles selbst (inkl. `<html>` und Abbinder).

Die Platzhalter-Knöpfe stehen direkt bei den Bausteinen; ein Klick fügt den Platzhalter ins
zuletzt benutzte Feld (Betreff, Text- oder HTML-Baustein) an der Cursorposition ein.

## Platzhalter

`{{ name }}`, `{{ ausgabe }}` und `{{ betreff }}` funktionieren in **beiden** Modi und werden
je Empfänger einzeln gefüllt.

⚠️ Das erledigt bewusst dieses Modul (`Support\Platzhalter`), nicht der Core: `VorlagenMailer`
ersetzt Platzhalter in der *Vorlage*, in einem einzigen Durchgang – was dabei eingesetzt wird
(bei uns die ganze Ausgabe als `{{ inhalt }}`), wird nicht noch einmal durchsucht. Ohne diesen
Zwischenschritt stünde `{{ name }}` wörtlich in der Mail.

## Im Maillog

Jede Newsletter-Mail erscheint im Maillog des Core (Verwaltung → Maillog) mit dem Auslöser
**Newsletter** – auch die „komplett eigener Code"-Mails, die über `Mail::html()` statt über eine
Vorlage laufen. Möglich macht das ein interner Header, den der Core beim Einliefern ausliest und
wieder entfernt (`VorlagenMailer::quelleMarkieren()`, ab dem entsprechenden Core-Stand).

## Zustellung je Empfänger (auf der Ausgaben-Seite)

Unterhalb einer freigegebenen Ausgabe steht **an wen sie ging bzw. geht**, mit dem echten
Stand aus dem Maillog des Core – seitenweise (50/Seite):

| Status | Bedeutung |
|---|---|
| Versendet | Vom Mailserver angenommen (mit Zeitpunkt). |
| Im Ausgangskorb | Eingeliefert, wartet auf den gedrosselten Versand. |
| Wartet auf Einlieferung | Noch nicht an den Ausgangskorb übergeben. |
| Versand fehlgeschlagen | Nach mehreren Versuchen aufgegeben (mit Fehlertext). |
| Übersprungen | Nie verschickt (gesperrt / keine echte Adresse), mit Grund. |

Damit die Seite genau ihre eigenen Maillog-Zeilen findet, schreibt der Versand je Mail eine
Referenz `newsletter:<ausgabe>:<empfänger>` in die Core-Spalte `mail_outbox.referenz` (über den
Header `X-Intranet-Referenz`). Ein Betreff-Vergleich wäre unzuverlässig, wenn zwei Ausgaben
denselben Betreff tragen.

⚠️ Braucht **Core mit der `mail_outbox.referenz`-Spalte**. Fehlt sie (älterer Core), bleibt die
Übersicht beim Stand „Eingeliefert", statt mit einem Fehler auszusteigen.

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

**Versandtermin:** Bei der Freigabe lässt sich ein frühester Versandzeitpunkt angeben
(`versand_ab`, leer = sofort). Die Empfängerliste steht ab der Freigabe fest; der Command lässt
die Ausgabe liegen, bis der Termin erreicht ist (Status „Geplant ab …").

## Zielgruppen

Auswählbar sind **alle Rollen, die es in dieser Instanz gibt** – das Modul bringt keine
eigenen mit und kennt keine fest verdrahteten Gruppen. Wer im Admin-Panel eine neue Rolle
anlegt, kann sie sofort anschreiben. Zusätzlich gibt es „Alle Benutzer".

Immer ausgeschlossen: gesperrte Konten und Adressen, an die nicht zugestellt werden kann.
Die Aufschlüsselung („erreicht 412 von 917") steht schon im Formular, **bevor** man freigibt.

### Eigene Gruppen (manuell)

Wer eine Ausgabe anlegt, kann sich über **„+ Eigene Gruppe"** (Modal) persönliche Zielgruppen
zusammenstellen (Tabelle `newsletter_gruppen`, je Benutzer): Rollen und einzelne Kontakte
**einschließen**, Rollen und Kontakte **ausschließen** – Ausschluss gewinnt. Die Gruppe gehört
ihrem Ersteller; nur er sieht und pflegt sie (Bearbeiten/Löschen im Modal). In der Auswahl stehen
manuelle Gruppen immer ganz oben mit der Kennzeichnung *manuell*.

In der Ausgabe steht die Gruppe als Zielgruppe `gruppe:<id>`. Nutzt eine Ausgabe die Gruppe eines
anderen Benutzers, bleibt der Haken beim Speichern erhalten (Anzeige „von <Name>", nicht
bearbeitbar). Eine gelöschte Gruppe löst sich zu niemandem auf und fällt beim nächsten Speichern
aus der Ausgabe.

## Vorlagen

Unter *Verwaltung → Mailvorlagen* meldet das Modul **einen** Eintrag an:

| Schlüssel | Was es ist |
|---|---|
| `newsletter` | Was um **jede** Ausgabe steht: Anrede, Abbinder. Der Betreff wird über `{{ betreff }}` durchgereicht – wer will, macht daraus `[Schule] {{ betreff }}`. |

Der **Rahmen** (Kopf, Logo, Fußzeile) liegt seit v1.8 nicht mehr in der Verwaltung, sondern
im Modul selbst – siehe unten. Ohne gewählte Vorlage gilt der allgemeine Rahmen des Intranets
(`_rahmen`, derselbe wie bei Systemmails).

### Mailvorlagen der Redaktion

Wer das Modul bedient, soll keinen Zugang zum Adminbereich brauchen. Deshalb gibt es im
Modul den Menüpunkt **Mailvorlagen** (Tabelle `newsletter_vorlagen`): eigene Rahmen mit den
Platzhaltern `{{ inhalt }}`, `{{ titel }}`, `{{ logo }}`, `{{ jahr }}`, mit Quelltext,
Textfassung und Live-Vorschau (mit Beispiel-Ausgabe darin). Eine neue Vorlage startet mit dem
mitgelieferten Newsletter-Rahmen (640 px, farbiger Streifen).

- Jede Ausgabe wählt im Editor ihre Vorlage. **Keine gewählt = allgemeiner Rahmen des Intranets.**
- Eine Vorlage kann *Standard für neue Ausgaben* sein (höchstens eine). Ohne Markierung
  starten neue Ausgaben im allgemeinen Rahmen.
- Anrede und Abbinder (`newsletter`) kommen weiterhin aus der Verwaltung.
- Wird eine Vorlage gelöscht, fallen die betroffenen Ausgaben auf den allgemeinen Rahmen
  zurück (Spalte `vorlage_id` ohne Fremdschlüssel).

**Umzug aus der Verwaltung (Migration `2026_09_15_130000`):** Die Vorlage „Newsletter-Rahmen"
wird im Modul angelegt – in der Fassung, die unter `_rahmen_newsletter` in der Verwaltung
angepasst war, sonst als mitgelieferter Rahmen. Nicht als Standard. Die alte Zeile in
`mail_vorlagen` bleibt stehen, wird aber nicht mehr angezeigt.

Technisch meldet `Zusteller::imEigenenRahmen()` die gewählte Vorlage für den jeweiligen
Render-Aufruf als Rahmen `_rahmen_newsletter_eigen` im Core-Register an und biegt die Vorlage
`newsletter` darauf um – der Core selbst bleibt unverändert. Das braucht **Core ≥
`VorlagenDefinition` mit `$rahmen`-Feld**.

## Absender je Ausgabe

Jede Ausgabe kann einen eigenen **Absendernamen** und eine **Antwort-an-Adresse** tragen
(im Editor unter Titel/Betreff). Nur der angezeigte Name ändert sich – die Absenderadresse
bleibt die der Instanz (`MAIL_FROM_ADDRESS`), weil eine fremde Absenderadresse ohne
SPF/DKIM der eigenen Domain im Spam landet. Antworten gehen dann z. B. ins Postfach der
Redaktion statt ins Systempostfach. Beides leer = Standard der Instanz.

**Eigenes Postfach:** Hat die Verwaltung unter Maillog → SMTP-Absender Konten angelegt,
bietet der Editor sie im Dropdown „Absender" an. Die Ausgabe geht dann über genau dieses
Postfach raus, mit dessen Absenderadresse; der Wechsel im Dropdown setzt Name und Antwort-an
auf die Vorgaben des Kontos, beide bleiben editierbar. Ist das Konto beim Versand gelöscht
oder abgeschaltet, fällt die Ausgabe auf den Standard zurück (steht im Log und auf der
Ausgaben-Seite). Braucht einen Core mit `App\Models\MailKonto`; ein älterer Core zeigt
das Dropdown nicht.

Eine Absender-Zeile in der Verwaltung (Maillog → Absender je Auslöser, Modul *Core*,
Auslöser *Newsletter*) gewinnt beim Einliefern über beides.

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
