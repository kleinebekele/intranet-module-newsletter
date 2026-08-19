# Newsletter-Modul – Konzept

**Stand:** 2026-07-20 · Konzeptphase, noch keine Zeile Code
**Paket:** `do1emu/module-newsletter` (öffentlich, Packagist) · Repo `plattform\intranet-module-newsletter`
**Modul-Key:** `newsletter` · Routen `module.newsletter.*`

Generisch gebaut, Auslöser war eine Schule (Rundmail an Lehrer/Eltern/Mitarbeiter).
Jede andere Instanz kann es ohne Umbau mitnutzen – Zielgruppen sind schlicht „die Rollen, die es in
dieser Instanz gibt".

---

## Leitgedanke

Der Newsletter erfindet **keinen zweiten Mailweg**. Er schreibt seine Mails in dieselbe
`mail_outbox` wie jede andere Mail des Intranets und nutzt dieselbe Vorlagen-Mechanik. Alles,
was das Mail-System heute schon kann, gilt damit automatisch auch für den Newsletter.

Was daraus **geschenkt** kommt (nichts davon muss gebaut werden):

- **Drosselung.** `mail:ausliefern` läuft minütlich und hält das Stundenlimit ein. Ein Newsletter
  an 900 Leute bei Limit 250 rieselt über ~4 Stunden raus, statt den Mailserver zu fluten.
- **Vorfahrt für Wichtiges.** Newsletter laufen mit Priorität 0, 2FA- und Passwort-Mails mit 10 –
  niemand wartet 4 Stunden auf seinen Anmeldecode, weil gerade der Newsletter läuft.
- **Künstliche Adressen fallen still raus.** `Zustellbarkeit` filtert `.intern` beim Einliefern
  *und* beim Versenden. Auch mehrere hundert Konten ohne echte Adresse sprengen nichts.
- **Vorlagen-Komfort.** Betreff + HTML- und Text-Fassung, Platzhalter, Live-Vorschau, Testmail,
  „Standard wiederherstellen" – der Newsletter erbt das komplette Backend unter *Mailvorlagen*.

⚠️ **Voraussetzung:** Ohne Cron für `schedule:run` geht bei aktivem `MAIL_OUTBOX` **gar keine**
Mail raus. Der Cron-Eintrag muss auf jedem Server einmalig angelegt sein.

---

## Der eigene Rahmen (kleine Core-Änderung)

Heute ist der Rahmen fest verdrahtet: es gibt genau einen (`_rahmen`, Konstante
`VorlagenDefinition::RAHMEN`), und `VorlagenMailer` bettet **jede** Vorlage darin ein.

Ein Newsletter soll anders aussehen als eine Einladungsmail – breiterer Kopf, Ausgabentitel,
Fußzeile mit Abmeldehinweis. Deshalb:

> `VorlagenDefinition` bekommt ein optionales Feld **„welchen Rahmen nutze ich"**.
> Fehlt es, gilt wie bisher `_rahmen`. Das Newsletter-Modul registriert seinen eigenen
> `_rahmen_newsletter` und verweist darauf.

Rückwärtskompatibel – bestehende Vorlagen merken davon nichts. Der neue Rahmen taucht unter
*Verwaltung → Mailvorlagen* als ganz normaler, frei bearbeitbarer Eintrag auf, mit derselben
Vorschau und demselben „Standard wiederherstellen".

Betroffen im Core: `app/Mail/Vorlagen/VorlagenDefinition.php`,
`app/Mail/Vorlagen/VorlagenMailer.php` (Einbettung Z. 74-79), ggf. die Beispielwerte-Map in
`MailVorlageController.php:193-218`.

---

## Inhalt erfassen: Bausteine, kein HTML

Entscheidung 2026-07-20: **kein rohes HTML-Feld, kein WYSIWYG-Editor.**

Eine Ausgabe besteht aus Abschnitten, die man per Knopf hinzufügt, sortiert und wieder entfernt:

| Baustein | Felder |
|---|---|
| Überschrift | Text, Ebene (groß/klein) |
| Textabsatz | mehrzeiliger Text (Absätze bleiben Absätze, Links werden erkannt) |
| Bild | Upload + Alternativtext, optional verlinkt |
| Knopf | Beschriftung + Ziel-URL |
| Trennlinie | – |

Gespeichert als JSON in der Kampagne. Das Modul rendert daraus das mailsichere,
tabellenbasierte Inline-Style-HTML **und** die Klartext-Fassung – analog zu dem, was
`VorlagenRegister` heute von Hand schreibt.

**Warum so:** Wer den Newsletter schreibt, ist das Schulbüro, nicht Emanuel. Bausteine bedeuten:
keine spitze Klammer sichtbar, das Layout ist nicht zerschießbar, und Outlook bekommt garantiert
HTML, das es versteht. WYSIWYG-Editoren erzeugen Markup, das Outlook teils ignoriert, und
eingefügter Word-Text schleppt kaputtes Markup ein.

Das gerenderte HTML geht als Platzhalter `{{ inhalt }}` in den Newsletter-Rahmen – dieselbe
Einsetzstelle, die der Core-Rahmen schon kennt.

---

## Datenmodell

**`newsletter_kampagnen`**
`id`, `titel` (intern), `betreff`, `bausteine` (json), `zielgruppen` (json: Rollen-Keys bzw.
`alle`), `status` (`entwurf` | `versendet`), `erstellt_von` (user_id), `freigegeben_am`,
`freigegeben_von`, `versand_beendet_am`, `timestamps`.

**`newsletter_empfaenger`** – eine Zeile je Person und Ausgabe
`id`, `kampagne_id`, `user_id` (nullable), `email`, `status`
(`wartend` | `eingeliefert` | `uebersprungen`), `grund` (z. B. „unzustellbar", „gesperrt"),
`eingeliefert_am`, unique `[kampagne_id, user_id]`.

Diese zweite Tabelle ist der eigentliche Gewinn: sie beantwortet später **„hat Familie X den
Newsletter bekommen?"** und macht den Versand *wiederaufnehmbar*, wenn ein Lauf abbricht.
Vorbild ist der Einladungs-Puffer (`app/Models/Einladung.php`), der genau so arbeitet.

**`newsletter_abmeldungen`** – siehe offene Punkte, evtl. nur ein Flag am User.

---

## Ablauf

1. **Anlegen** – Titel, Betreff, Bausteine. Status `entwurf`, jederzeit weiter bearbeitbar.
2. **Zielgruppen wählen** – Mehrfachauswahl über die Rollen (in einer Schule etwa `teacher`, `parent`,
   `staff`, `student`) oder „alle". Die Auswahl zeigt sofort **„erreicht 412 von 917"** und
   erklärt die Differenz (gesperrt, nur künstliche Adresse, abgemeldet). Keine Überraschungen
   nach dem Absenden.
3. **Vorschau + Testmail** an sich selbst – über dieselbe Mechanik wie bei den Mailvorlagen.
4. **Freigeben** – bewusster zweiter Klick mit Empfängerzahl in der Rückfrage. Erst hier werden
   die `newsletter_empfaenger`-Zeilen erzeugt.
5. **Versand** – ein Scheduler-Command arbeitet die wartenden Zeilen in Häppchen ab und ruft je
   Empfänger `VorlagenMailer::senden()`. Ab da übernimmt die Outbox samt Stundenlimit.
6. **Verlauf** – Liste der Ausgaben mit Datum, Zielgruppen, „x von y zugestellt".

Bewusst **kein** Sofortversand aus dem Formular heraus. Bei 900 Empfängern will man den Absprung
als eigenen, sichtbaren Schritt.

---

## Empfänger bestimmen

Grundmenge sind die Rollen aus `user_roles`. Schul-Rollen wie `student` / `teacher` / `staff` /
`parent` legt in der Regel ein Benutzer-Import an – das Modul setzt keine
Rollen, es liest sie nur.

Immer ausgeschlossen:
- `gesperrt_am IS NOT NULL`
- Adressen, die `Zustellbarkeit::zustellbar()` ablehnt (`.intern`)
- Abgemeldete (falls Opt-out kommt, s. u.)

Alternative für Eltern wäre `users_parents` (`User::has('children')`), strukturell sauberer als
eine Rolle. Vorerst nicht nötig – die Rolle `parent` reicht und ist instanzunabhängig.

---

## Offene Punkte

- **Abmeldung (Opt-out).** Für eine interne Schul-Rundmail an Mitglieder rechtlich vermutlich
  nicht zwingend, aber praktisch: ein Abmelde-Link in der Fußzeile spart Rückfragen. Braucht
  einen Token-Link ohne Anmeldung. Liefert das führende Fremdsystem ein Newsletter-Kennzeichen
  mit, kann es als Vorbelegung dienen.
- **Bild-Upload und absolute URLs.** Mail-Clients laden keine relativen Pfade. Der Core löst das
  beim Logo schon (`VorlagenMailer::logoBild()`), das Muster ist übernehmbar.
- **Anhänge** (PDF-Elternbrief) – naheliegender Wunsch, im ersten Wurf nicht vorgesehen. Die
  Outbox serialisiert die komplette Symfony-Mail, Anhänge wären technisch kein Problem, aber
  900× dasselbe PDF bläht die Tabelle auf. Eher: PDF hochladen, Link in den Newsletter.
- **Wer darf?** Vermutlich eine eigene Rolle „Redaktion" statt nur Admins. Über die
  Modul-Rollen-Sichtbarkeit (`module_menu_item_role`) ohne eigenen Code lösbar.
- **Zeitversetzter Versand** („Freitag 8 Uhr") – bewusst zurückgestellt, bis der einfache Fall
  läuft.
