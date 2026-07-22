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
use Intranet\Modules\Newsletter\Models\Kampagne;
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
                ->latest('id')
                ->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('newsletter::form', [
            'kampagne' => new Kampagne([
                'bausteine' => [],
                'zielgruppen' => [],
                'modus' => Kampagne::MODUS_BAUSTEINE,
                'mit_rahmen' => true,
            ]),
            'rollen' => Empfaengerkreis::rollen(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $kampagne = Kampagne::create($this->daten($request) + [
            'erstellt_von' => $request->user()->id,
        ]);

        return redirect()->route('module.newsletter.show', $kampagne)
            ->with('status', 'Ausgabe angelegt. Verschickt ist noch nichts.');
    }

    public function edit(Kampagne $kampagne): View
    {
        abort_unless($kampagne->istEntwurf(), 403);

        return view('newsletter::form', [
            'kampagne' => $kampagne,
            'rollen' => Empfaengerkreis::rollen(),
        ]);
    }

    public function update(Request $request, Kampagne $kampagne): RedirectResponse
    {
        abort_unless($kampagne->istEntwurf(), 403);

        $kampagne->update($this->daten($request));

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

        return view('newsletter::show', [
            'kampagne' => $kampagne->load('ersteller'),
            'fortschritt' => $kampagne->fortschritt(),
            'uebersicht' => $kampagne->istEntwurf()
                ? Empfaengerkreis::uebersicht($kampagne->zielgruppen ?? [])
                : null,
            'empfaenger' => $empfaenger,
        ]);
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

        $anzahl = $kampagne->freigeben($request->user());

        if ($anzahl === 0) {
            return back()->with('error', 'Kein erreichbarer Empfänger – bitte die Zielgruppen prüfen.');
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
            Empfaengerkreis::uebersicht($this->zielgruppen($request)),
        );
    }

    /**
     * Vorschau der Ausgabe, so wie sie im Postfach ankäme – mit dem, was
     * gerade im Formular steht (also auch ungespeichert).
     */
    public function vorschau(Request $request): JsonResponse
    {
        // Zusätzlich zur fertigen Mail der ROHE Inhalt: Den braucht der Knopf
        // „Aus Baukasten übernehmen" als Startpunkt für den Code-Modus. Die
        // gerahmte Fassung wäre dafür unbrauchbar – sie enthält das ganze
        // Dokument samt Kopf und Fuß.
        return response()->json(
            $this->rendernAusRequest($request) + $this->inhaltAusRequest($request),
        );
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
        Mail::html($fertig['html'], function ($nachricht) use ($daten, $fertig) {
            $nachricht->to($daten['an'])->subject('[TEST] '.$fertig['betreff'])->text($fertig['text']);
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

    /**
     * Die geprüften Formulardaten einer Ausgabe.
     *
     * Beide Fassungen werden gespeichert – die Bausteine UND der eigene Code.
     * `modus` entscheidet, welche gilt. Wer zwischen den Reitern wechselt, soll
     * seine Arbeit nicht verlieren.
     *
     * @return array<string, mixed>
     */
    private function daten(Request $request): array
    {
        $request->validate([
            'titel' => ['required', 'string', 'max:120'],
            'betreff' => ['required', 'string', 'max:200'],
            'modus' => ['required', Rule::in([Kampagne::MODUS_BAUSTEINE, Kampagne::MODUS_CODE])],
            'mit_rahmen' => ['nullable', 'boolean'],
            'zielgruppen' => ['array'],
            'zielgruppen.*' => ['string'],
            'bausteine' => ['nullable', 'string'],
            'html' => ['nullable', 'string'],
            'text' => ['nullable', 'string'],
        ]);

        return [
            'titel' => trim((string) $request->input('titel')),
            'betreff' => trim((string) $request->input('betreff')),
            'modus' => $request->input('modus'),
            // Nur im Code-Modus abwählbar; der Baukasten braucht den Rahmen immer.
            'mit_rahmen' => $request->input('modus') === Kampagne::MODUS_CODE
                ? $request->boolean('mit_rahmen')
                : true,
            'zielgruppen' => $this->zielgruppen($request),
            'bausteine' => $this->bausteine($request),
            'html' => $request->input('html'),
            'text' => $request->input('text'),
        ];
    }

    /**
     * Vorschau und Testmail rendern beide dasselbe: das, was GERADE im
     * Formular steht – auch ungespeichert, und je nach Reiter aus Bausteinen
     * oder aus eigenem Code.
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

        ['inhalt_html' => $html, 'inhalt_text' => $text] = $this->inhaltAusRequest($request);

        return Zusteller::rendern(
            $this->mailer,
            $this->mitRahmenAusRequest($request),
            (string) $request->input('betreff'),
            $html,
            $text,
            $werte,
        );
    }

    /**
     * Nutzt die im Formular gerade eingestellte Fassung den Rahmen? Der
     * Baukasten immer, der Code-Modus nur, wenn der Schalter an ist.
     */
    private function mitRahmenAusRequest(Request $request): bool
    {
        return $request->input('modus') !== Kampagne::MODUS_CODE
            || $request->boolean('mit_rahmen');
    }

    /**
     * Der Inhalt der Ausgabe – ohne Rahmen und ohne Anrede, so wie er in der
     * Spalte `html`/`bausteine` steht.
     *
     * @return array{inhalt_html: string, inhalt_text: string}
     */
    private function inhaltAusRequest(Request $request): array
    {
        if ($request->input('modus') === Kampagne::MODUS_CODE) {
            return [
                'inhalt_html' => (string) $request->input('html'),
                'inhalt_text' => (string) $request->input('text'),
            ];
        }

        $bausteine = $this->bausteine($request);

        return [
            'inhalt_html' => Bausteine::alsHtml($bausteine),
            'inhalt_text' => Bausteine::alsText($bausteine),
        ];
    }

    /**
     * Nur Zielgruppen übernehmen, die es wirklich gibt. Sonst stünde in der
     * Ausgabe eine Rolle, die niemand mehr hat – und niemand bekäme sie.
     *
     * @return array<int, string>
     */
    private function zielgruppen(Request $request): array
    {
        $erlaubt = Empfaengerkreis::rollen()
            ->pluck('role_id')
            ->push(Empfaengerkreis::ALLE)
            ->all();

        $gewaehlt = array_filter((array) $request->input('zielgruppen', []), 'is_string');

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
