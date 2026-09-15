<?php

namespace Intranet\Modules\Newsletter\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Mail\Vorlagen\VorlagenMailer;
use App\Models\MailOutbox;
use App\Support\Zustellbarkeit;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Intranet\Modules\Newsletter\Models\Empfaenger;
use Intranet\Modules\Newsletter\Models\Gruppe;
use Intranet\Modules\Newsletter\Models\Kampagne;
use Intranet\Modules\Newsletter\Models\Vorlage;
use Intranet\Modules\Newsletter\Support\Bausteine;
use Intranet\Modules\Newsletter\Support\Empfaengerkreis;
use Intranet\Modules\Newsletter\Support\Zusteller;

/**
 * Newsletter-Ausgaben anlegen, ansehen und freigeben.
 */
class NewsletterController extends Controller
{
    public function __construct(private VorlagenMailer $mailer) {}

    public function index(): View
    {
        return view('newsletter::index', [
            'kampagnen' => Kampagne::query()
                ->with('ersteller')
                ->withCount([
                    'empfaenger',
                    'empfaenger as eingeliefert_count' => fn ($q) => $q->where('status', Empfaenger::EINGELIEFERT),
                ])
                // Wann ging die erste Mail raus? (Einlieferung in den Ausgangskorb)
                ->withMin('empfaenger as erste_einlieferung', 'eingeliefert_am')
                ->latest('id')
                ->paginate(20),
        ]);
    }

    public function create(Request $request): View
    {
        $kampagne = new Kampagne([
            'bausteine' => [],
            'zielgruppen' => [],
            'mit_rahmen' => true,
            // Die Standardvorlage der Redaktion vorbelegen – oder null für
            // den allgemeinen Rahmen des Intranets.
            'vorlage_id' => Vorlage::standard()?->id,
        ]);

        return view('newsletter::form', $this->formularDaten($request, $kampagne));
    }

    public function store(Request $request): RedirectResponse
    {
        $kampagne = Kampagne::create($this->daten($request) + [
            'erstellt_von' => $request->user()->id,
        ]);

        return redirect()->route('module.newsletter.show', $kampagne)
            ->with('status', 'Ausgabe angelegt. Verschickt ist noch nichts.');
    }

    public function edit(Request $request, Kampagne $kampagne): View
    {
        abort_unless($kampagne->istEntwurf(), 403);

        return view('newsletter::form', $this->formularDaten($request, $kampagne));
    }

    /**
     * Alles, was das Formular neben der Ausgabe braucht – u. a. die manuellen
     * Gruppen: die EIGENEN (bearbeitbar) und fremde, die diese Ausgabe schon
     * nennt (nur sichtbar, damit der Haken nicht verloren geht).
     *
     * @return array<string, mixed>
     */
    private function formularDaten(Request $request, Kampagne $kampagne): array
    {
        $eigene = Gruppe::von($request->user())->orderBy('name')->get();

        $genannt = array_values(array_filter(array_map(
            fn ($z) => is_string($z) ? Gruppe::idAus($z) : null,
            (array) old('zielgruppen', $kampagne->zielgruppen ?? []),
        )));
        $fremde = $genannt === []
            ? collect()
            : Gruppe::with('besitzer:id,name')->whereIn('id', $genannt)->where('user_id', '!=', $request->user()->id)->get();

        return [
            'kampagne' => $kampagne,
            'rollen' => Empfaengerkreis::rollen(),
            'konten' => Zusteller::konten(),
            'vorlagen' => Vorlage::orderBy('name')->get(),
            'gruppen' => $eigene->map(fn (Gruppe $g) => $g->fuerFormular())->values(),
            'fremdeGruppen' => $fremde->map(fn (Gruppe $g) => [
                'kennung' => $g->kennung(),
                'name' => $g->name,
                'besitzer' => (string) ($g->besitzer?->name ?? '–'),
            ])->values(),
        ];
    }

    public function update(Request $request, Kampagne $kampagne): RedirectResponse
    {
        abort_unless($kampagne->istEntwurf(), 403);

        $kampagne->update($this->daten($request, $kampagne));

        return redirect()->route('module.newsletter.show', $kampagne)
            ->with('status', 'Ausgabe gespeichert.');
    }

    public function show(Kampagne $kampagne): View
    {
        // Empfänger seitenweise, jede Zeile mit dem echten Zustellstatus aus dem
        // Maillog des Core angereichert (an wen ging/geht die Ausgabe raus).
        $empfaenger = $kampagne->istEntwurf()
            ? null
            : $this->empfaengerMitStatus($kampagne);

        // Namen der manuellen Gruppen und Rollen für die Zielgruppen-Anzeige.
        $gruppenIds = array_values(array_filter(array_map(
            fn ($z) => is_string($z) ? Gruppe::idAus($z) : null,
            $kampagne->zielgruppen ?? [],
        )));
        $namen = Empfaengerkreis::rollen()->pluck('name', 'role_id')->all();
        foreach (($gruppenIds === [] ? collect() : Gruppe::whereIn('id', $gruppenIds)->get()) as $g) {
            $namen[$g->kennung()] = $g->name.' (manuell)';
        }

        return view('newsletter::show', [
            'zielgruppenNamen' => $namen,
            'kampagne' => $kampagne->load('ersteller'),
            'fortschritt' => $kampagne->fortschritt(),
            'uebersicht' => $kampagne->istEntwurf()
                ? Empfaengerkreis::uebersicht($kampagne->zielgruppen ?? [])
                : null,
            'empfaenger' => $empfaenger,
        ]);
    }

    /**
     * Die fertige Mail einer gespeicherten Ausgabe – mit Rahmen, Kopf und Fuß,
     * genau so, wie sie beim Empfänger ankommt. Wird auf der Detailseite in
     * einem Iframe gezeigt; Platzhalter sind mit den Daten des Betrachters gefüllt.
     */
    public function mailVorschau(Request $request, Kampagne $kampagne): \Illuminate\Http\Response
    {
        $fertig = Zusteller::rendern(
            $this->mailer,
            $kampagne->mitRahmen(),
            (string) $kampagne->betreff,
            $kampagne->alsHtml(),
            $kampagne->alsText(),
            [
                'name' => $request->user()->name,
                'betreff' => (string) $kampagne->betreff,
                'ausgabe' => (string) $kampagne->titel,
            ],
            $kampagne->vorlage(),
        );

        return response($fertig['html'])
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->header('X-Frame-Options', 'SAMEORIGIN');
    }

    /**
     * Die Empfänger dieser Ausgabe (seitenweise), jeder mit seinem echten
     * Zustellstatus aus dem Ausgangskorb des Core.
     *
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    private function empfaengerMitStatus(Kampagne $kampagne)
    {
        $seite = $kampagne->empfaenger()
            ->with('user:id,name')
            ->orderBy('id')
            ->paginate(50);

        // Für genau die Empfänger DIESER Seite die zugehörigen Maillog-Zeilen
        // holen – über die Referenz, die der Versand mitgeschrieben hat. Nur die
        // leichten Spalten (die serialisierte Nachricht bleibt außen vor).
        $zustellungen = collect();

        if ($this->outboxKenntReferenz() && $seite->isNotEmpty()) {
            $referenzen = $seite->getCollection()
                ->map(fn (Empfaenger $e) => $kampagne->mailReferenz($e->id))
                ->all();

            $zustellungen = MailOutbox::query()
                ->whereIn('referenz', $referenzen)
                ->get(['referenz', 'status', 'versendet_am', 'fehler'])
                ->keyBy('referenz');
        }

        return $seite->through(fn (Empfaenger $e) => $this->zustellZeile($kampagne, $e, $zustellungen));
    }

    /**
     * Eine Zeile der Zustell-Übersicht: Name, Adresse und ein einheitlich
     * eingefärbter Status, der Modul-Sicht (übersprungen/wartet) und echte
     * Ausgangskorb-Sicht (versendet/fehlgeschlagen) zusammenführt.
     *
     * @param  \Illuminate\Support\Collection<string, MailOutbox>  $zustellungen
     * @return array{name: string, email: string, label: string, farbe: string, detail: ?string, zeit: ?\Illuminate\Support\Carbon}
     */
    private function zustellZeile(Kampagne $kampagne, Empfaenger $empfaenger, $zustellungen): array
    {
        $basis = [
            'name' => $empfaenger->user?->name ?? '—',
            'email' => $empfaenger->email,
            'zeit' => $empfaenger->eingeliefert_am,
        ];

        // Zustände, die den Ausgangskorb nie erreicht haben – die Wahrheit steht
        // dann in der Empfänger-Zeile selbst.
        if ($empfaenger->status === Empfaenger::UEBERSPRUNGEN) {
            return $basis + ['label' => 'Übersprungen', 'farbe' => 'gray', 'detail' => $empfaenger->grund, 'zeit' => null];
        }

        if ($empfaenger->status === Empfaenger::FEHLER) {
            return $basis + ['label' => 'Fehler beim Einliefern', 'farbe' => 'red', 'detail' => $empfaenger->grund, 'zeit' => null];
        }

        if ($empfaenger->status === Empfaenger::WARTEND) {
            return $basis + ['label' => 'Wartet auf Einlieferung', 'farbe' => 'amber', 'detail' => null, 'zeit' => null];
        }

        // Eingeliefert: der echte Stand kommt aus dem Ausgangskorb.
        $mail = $zustellungen->get($kampagne->mailReferenz($empfaenger->id));

        if (! $mail) {
            // Keine Maillog-Zeile (z. B. Ausgangskorb abgeschaltet → Sofortversand,
            // oder Zeile aufgeräumt). Mehr als „übergeben" wissen wir dann nicht.
            return $basis + ['label' => 'Eingeliefert', 'farbe' => 'indigo', 'detail' => null];
        }

        return $basis + match ($mail->status) {
            MailOutbox::VERSENDET => ['label' => 'Versendet', 'farbe' => 'green', 'detail' => null, 'zeit' => $mail->versendet_am],
            MailOutbox::FEHLGESCHLAGEN => ['label' => 'Versand fehlgeschlagen', 'farbe' => 'red', 'detail' => $mail->fehler],
            default => ['label' => 'Im Ausgangskorb', 'farbe' => 'amber', 'detail' => null],
        };
    }

    /**
     * Kennt der Core die Referenz-Spalte schon? Ein älterer Core (vor dem
     * Referenz-Feature) hat sie nicht – dann bleibt die Zustell-Übersicht bei
     * „eingeliefert", statt mit einem DB-Fehler auszusteigen. Einmal je Anfrage.
     */
    private function outboxKenntReferenz(): bool
    {
        static $vorhanden = null;

        return $vorhanden ??= Schema::hasColumn('mail_outbox', 'referenz');
    }

    public function destroy(Kampagne $kampagne): RedirectResponse
    {
        abort_unless($kampagne->istEntwurf(), 403);

        $titel = $kampagne->titel;
        $kampagne->delete();

        return redirect()->route('module.newsletter.index')
            ->with('status', "Entwurf „{$titel}\" gelöscht.");
    }

    /**
     * Der Absprung: Empfängerliste festschreiben, Versand anstoßen.
     */
    public function freigeben(Request $request, Kampagne $kampagne): RedirectResponse
    {
        if (! $kampagne->istEntwurf()) {
            return back()->with('error', 'Diese Ausgabe wurde bereits freigegeben.');
        }

        if (! $kampagne->hatInhalt()) {
            return back()->with('error', 'Die Ausgabe hat noch keinen Inhalt.');
        }

        // Optionaler frühester Versandzeitpunkt (datetime-local aus dem Formular).
        $request->validate(['versand_ab' => ['nullable', 'date']]);
        $versandAb = $request->filled('versand_ab')
            ? \Illuminate\Support\Carbon::parse($request->input('versand_ab'))
            : null;

        $anzahl = $kampagne->freigeben($request->user(), $versandAb);

        if ($anzahl === 0) {
            return back()->with('error', 'Kein erreichbarer Empfänger – bitte die Zielgruppen prüfen.');
        }

        $kampagne->refresh();

        if ($kampagne->wartetAufTermin()) {
            return redirect()->route('module.newsletter.show', $kampagne)->with(
                'status',
                "Freigegeben: {$anzahl} Empfänger sind vorgemerkt. Der Versand startet am "
                .$kampagne->versand_ab->format('d.m.Y \u\m H:i').' Uhr.',
            );
        }

        return redirect()->route('module.newsletter.show', $kampagne)->with(
            'status',
            "Freigegeben: {$anzahl} Empfänger sind vorgemerkt. Der Versand läuft ab jetzt "
            .'gedrosselt über den Ausgangskorb.',
        );
    }

    /**
     * „Erreicht 412 von 917" – noch im Formular, vor dem Absenden.
     */
    public function reichweite(Request $request): JsonResponse
    {
        return response()->json(
            Empfaengerkreis::uebersicht($this->zielgruppen($request, null, true)),
        );
    }

    /**
     * Vorschau der Ausgabe, so wie sie im Postfach ankäme – mit dem, was
     * gerade im Formular steht (also auch ungespeichert).
     */
    public function vorschau(Request $request): JsonResponse
    {
        return response()->json($this->rendernAusRequest($request));
    }

    /**
     * Testmail an eine frei eingegebene Adresse – dieselbe Mechanik wie unter
     * Mailvorlagen, inklusive [TEST]-Präfix im Betreff.
     */
    public function testmail(Request $request): JsonResponse
    {
        $daten = $request->validate(['an' => ['required', 'email']]);

        if (! Zustellbarkeit::zustellbar($daten['an'])) {
            return response()->json(['ok' => false, 'meldung' => 'An diese Adresse kann nicht zugestellt werden.'], 422);
        }

        $fertig = $this->rendernAusRequest($request);

        // Bewusst NICHT über den Zusteller: Die Testmail trägt ein [TEST] im
        // Betreff und geht an genau eine frei gewählte Adresse. Den Auslöser
        // markieren wir trotzdem, damit sie im Maillog als „Newsletter" steht.
        $absenderName = trim((string) $request->input('absender_name')) ?: null;
        $antwortAn = trim((string) $request->input('antwort_an')) ?: null;
        $konto = $request->filled('mail_konto_id')
            ? Zusteller::konten()->firstWhere('id', (int) $request->input('mail_konto_id'))
            : null;

        Mail::html($fertig['html'], function ($nachricht) use ($daten, $fertig, $absenderName, $antwortAn, $konto) {
            $nachricht->to($daten['an'])->subject('[TEST] '.$fertig['betreff'])->text($fertig['text']);
            Zusteller::absenderSetzen($nachricht, $absenderName, $antwortAn, $konto);
            VorlagenMailer::quelleMarkieren($nachricht, Zusteller::QUELLE);
        });

        return response()->json([
            'ok' => true,
            'meldung' => "Testmail an {$daten['an']} liegt im Ausgangskorb.",
        ]);
    }

    /**
     * Bild für einen Baustein hochladen.
     *
     * Eine Mail braucht eine ABSOLUTE Adresse – im Postfach gibt es keine Seite,
     * gegen die ein relativer Pfad auflösen könnte. Gleiches Vorgehen wie beim
     * Logo im Core-Rahmen.
     */
    public function bild(Request $request): JsonResponse
    {
        $request->validate([
            'bild' => ['required', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:4096'],
        ]);

        $pfad = $request->file('bild')->store('newsletter', 'public');
        $url = url(parse_url(Storage::disk('public')->url($pfad), PHP_URL_PATH));

        return response()->json(['url' => $url]);
    }

    /** Wert des Rahmen-Dropdowns für „keine – nur eigener Code". */
    public const RAHMEN_KEINER = 'keine';

    /**
     * Die geprüften Formulardaten einer Ausgabe.
     *
     * Der Rahmen kommt als EIN Feld `rahmen`: leer = allgemeiner Rahmen des
     * Intranets, `keine` = ohne Rahmen (die Bausteine sind die ganze Mail),
     * Zahl = eigene Vorlage aus dem Menüpunkt „Mailvorlagen".
     *
     * @return array<string, mixed>
     */
    private function daten(Request $request, ?Kampagne $kampagne = null): array
    {
        $request->validate([
            'titel' => ['required', 'string', 'max:120'],
            'betreff' => ['required', 'string', 'max:200'],
            'absender_name' => ['nullable', 'string', 'max:120'],
            'antwort_an' => ['nullable', 'email', 'max:191'],
            'mail_konto_id' => ['nullable', 'integer', Rule::in(Zusteller::konten()->pluck('id')->all())],
            'rahmen' => ['nullable', 'string', function (string $attribut, mixed $wert, \Closure $fehler): void {
                if ($wert === '' || $wert === self::RAHMEN_KEINER) {
                    return;
                }
                if (! ctype_digit((string) $wert) || ! Vorlage::whereKey((int) $wert)->exists()) {
                    $fehler('Diese Mailvorlage gibt es nicht (mehr).');
                }
            }],
            'zielgruppen' => ['array'],
            'zielgruppen.*' => ['string'],
            'bausteine' => ['nullable', 'string'],
        ]);

        return [
            'titel' => trim((string) $request->input('titel')),
            'betreff' => trim((string) $request->input('betreff')),
            'absender_name' => trim((string) $request->input('absender_name')) ?: null,
            'antwort_an' => trim((string) $request->input('antwort_an')) ?: null,
            'mail_konto_id' => $request->filled('mail_konto_id') ? (int) $request->input('mail_konto_id') : null,
            'vorlage_id' => $this->vorlageIdAusRequest($request),
            'mit_rahmen' => $this->mitRahmenAusRequest($request),
            'modus' => Kampagne::MODUS_BAUSTEINE,
            'zielgruppen' => $this->zielgruppen($request, $kampagne),
            'bausteine' => $this->bausteine($request),
        ];
    }

    /**
     * Vorschau und Testmail rendern beide dasselbe: das, was GERADE im
     * Formular steht – auch ungespeichert.
     *
     * @return array{betreff: string, html: string, text: string}
     */
    private function rendernAusRequest(Request $request): array
    {
        $werte = [
            'name' => $request->user()->name,
            'betreff' => (string) $request->input('betreff'),
            'ausgabe' => (string) $request->input('titel'),
        ];

        $bausteine = $this->bausteine($request);
        $vorlageId = $this->vorlageIdAusRequest($request);

        return Zusteller::rendern(
            $this->mailer,
            $this->mitRahmenAusRequest($request),
            (string) $request->input('betreff'),
            Bausteine::alsHtml($bausteine),
            Bausteine::alsText($bausteine),
            $werte,
            $vorlageId ? Vorlage::find($vorlageId) : null,
        );
    }

    /** Rahmen-Dropdown: alles außer „keine" bekommt einen Rahmen. */
    private function mitRahmenAusRequest(Request $request): bool
    {
        return (string) $request->input('rahmen', '') !== self::RAHMEN_KEINER;
    }

    /** Rahmen-Dropdown: nur eine Zahl ist eine eigene Vorlage. */
    private function vorlageIdAusRequest(Request $request): ?int
    {
        $wert = (string) $request->input('rahmen', '');

        return ctype_digit($wert) ? (int) $wert : null;
    }

    /**
     * Nur Zielgruppen übernehmen, die es wirklich gibt. Sonst stünde in der
     * Ausgabe eine Rolle, die niemand mehr hat – und niemand bekäme sie.
     *
     * Manuelle Gruppen (`gruppe:<id>`): die eigenen – und fremde nur, wenn die
     * Ausgabe sie schon vorher nannte (beim Speichern durch einen anderen
     * Benutzer geht der Haken sonst verloren). Für die Reichweiten-Vorschau
     * (ohne Ausgabe) zählt jede vorhandene Gruppe – das ist nur eine Zahl.
     *
     * @return array<int, string>
     */
    private function zielgruppen(Request $request, ?Kampagne $kampagne = null, bool $nurVorschau = false): array
    {
        $gewaehlt = array_values(array_filter((array) $request->input('zielgruppen', []), 'is_string'));

        $erlaubt = Empfaengerkreis::rollen()
            ->pluck('role_id')
            ->push(Empfaengerkreis::ALLE)
            ->all();

        $gruppenIds = array_values(array_filter(array_map(fn ($z) => Gruppe::idAus($z), $gewaehlt)));

        if ($gruppenIds !== []) {
            $abfrage = Gruppe::whereIn('id', $gruppenIds);

            if (! $nurVorschau) {
                $bisher = array_values(array_filter(array_map(
                    fn ($z) => is_string($z) ? Gruppe::idAus($z) : null,
                    $kampagne?->zielgruppen ?? [],
                )));
                $abfrage->where(fn ($w) => $w->where('user_id', $request->user()->id)->orWhereIn('id', $bisher ?: [0]));
            }

            foreach ($abfrage->pluck('id') as $id) {
                $erlaubt[] = Gruppe::PRAEFIX.$id;
            }
        }

        return array_values(array_intersect($gewaehlt, $erlaubt));
    }

    /**
     * Die Bausteine kommen als JSON aus einem versteckten Feld – der Editor
     * baut sie im Browser zusammen, und beim Umsortieren wären durchnummerierte
     * Formularfelder eine Fehlerquelle.
     *
     * @return array<int, array<string, mixed>>
     */
    private function bausteine(Request $request): array
    {
        $roh = json_decode((string) $request->input('bausteine', '[]'), true);

        return is_array($roh) ? Bausteine::bereinigen($roh) : [];
    }
}
