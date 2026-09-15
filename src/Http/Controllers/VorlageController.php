<?php

namespace Intranet\Modules\Newsletter\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Mail\Vorlagen\VorlagenMailer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Intranet\Modules\Newsletter\Models\Kampagne;
use Intranet\Modules\Newsletter\Models\Vorlage;
use Intranet\Modules\Newsletter\Support\Zusteller;

/**
 * Eigene Mailvorlagen (Rahmen) der Redaktion – Menüpunkt „Mailvorlagen".
 *
 * Wer das Modul bedienen darf, darf hier Rahmen anlegen, ohne Zugang zum
 * Adminbereich. Ist keine Vorlage angelegt oder an einer Ausgabe keine gewählt,
 * gilt der allgemeine Rahmen des Intranets (Verwaltung → Mailvorlagen → Rahmen).
 */
class VorlageController extends Controller
{
    /** Platzhalter, die ein Rahmen kennt – dieselben wie `_rahmen_newsletter` im Core. */
    private const PLATZHALTER = [
        'inhalt' => 'Die Ausgabe selbst (nicht selbst eintippen)',
        'titel' => 'Haupttitel aus den Einstellungen',
        'logo' => 'Logo aus den Einstellungen (leer, wenn keins hinterlegt ist)',
        'jahr' => 'Aktuelles Jahr',
    ];

    public function __construct(private VorlagenMailer $mailer) {}

    public function index(): View
    {
        return view('newsletter::vorlagen.index', [
            'vorlagen' => Vorlage::query()
                ->withCount('kampagnen')
                ->orderByDesc('ist_standard')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function create(): View
    {
        // Startpunkt ist der mitgelieferte Newsletter-Rahmen – so muss niemand
        // bei einem leeren Feld anfangen.
        $rahmen = Zusteller::standardRahmen();

        return view('newsletter::vorlagen.form', [
            'vorlage' => new Vorlage(['name' => '', 'html' => $rahmen['html'], 'text' => $rahmen['text']]),
            'platzhalter' => self::PLATZHALTER,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $vorlage = Vorlage::create($this->daten($request));

        // Kein stiller Standard: Ohne ausdrückliche Markierung starten neue
        // Ausgaben im allgemeinen Rahmen des Intranets.
        if ($request->boolean('ist_standard')) {
            $vorlage->alsStandardSetzen();
        }

        return redirect()->route('module.newsletter.vorlagen.index')
            ->with('status', "Mailvorlage „{$vorlage->name}\" angelegt.");
    }

    public function edit(Vorlage $vorlage): View
    {
        return view('newsletter::vorlagen.form', [
            'vorlage' => $vorlage,
            'platzhalter' => self::PLATZHALTER,
        ]);
    }

    public function update(Request $request, Vorlage $vorlage): RedirectResponse
    {
        $vorlage->update($this->daten($request));

        if ($request->boolean('ist_standard')) {
            $vorlage->alsStandardSetzen();
        } elseif ($vorlage->ist_standard) {
            // Haken entfernt: neue Ausgaben starten wieder im allgemeinen Rahmen.
            $vorlage->forceFill(['ist_standard' => false])->save();
        }

        return redirect()->route('module.newsletter.vorlagen.index')
            ->with('status', "Mailvorlage „{$vorlage->name}\" gespeichert.");
    }

    /** Diese Vorlage bei neuen Ausgaben vorbelegen (es gibt höchstens eine). */
    public function standard(Vorlage $vorlage): RedirectResponse
    {
        $vorlage->alsStandardSetzen();

        return redirect()->route('module.newsletter.vorlagen.index')
            ->with('status', "„{$vorlage->name}\" ist jetzt die Standardvorlage.");
    }

    public function destroy(Vorlage $vorlage): RedirectResponse
    {
        $name = $vorlage->name;

        // Ausgaben, die darauf zeigten, fallen auf den allgemeinen Rahmen
        // zurück – ausdrücklich, nicht erst still beim Versand.
        Kampagne::where('vorlage_id', $vorlage->id)->update(['vorlage_id' => null]);
        $vorlage->delete();

        return redirect()->route('module.newsletter.vorlagen.index')
            ->with('status', "Mailvorlage „{$name}\" gelöscht. Betroffene Ausgaben nutzen wieder den allgemeinen Rahmen des Intranets.");
    }

    /**
     * Live-Vorschau: der Rahmen aus dem Formular mit einer Beispiel-Ausgabe
     * darin, so wie die fertige Mail aussähe. Nichts wird gespeichert.
     */
    public function vorschau(Request $request): JsonResponse
    {
        $attrappe = (object) [
            'html' => (string) $request->input('html'),
            'text' => (string) $request->input('text'),
        ];

        $fertig = Zusteller::rendern(
            $this->mailer,
            true,
            'Neues aus dem Haus',
            '<p style="margin:0 0 12px;font-size:17px;font-weight:bold;color:#1f2937;">Beispiel-Überschrift</p>'
                .'<p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:#374151;">'
                .'Hier stehen die Bausteine der jeweiligen Ausgabe. Dieser Text ist nur ein Platzhalter für die Vorschau.</p>',
            "Beispiel-Überschrift\n\nHier stehen die Bausteine der jeweiligen Ausgabe.",
            [
                'name' => $request->user()->name,
                'betreff' => 'Neues aus dem Haus',
                'ausgabe' => 'Beispiel-Ausgabe',
            ],
            $attrappe,
        );

        return response()->json($fertig);
    }

    /** @return array<string, mixed> */
    private function daten(Request $request): array
    {
        $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'html' => ['required', 'string'],
            'text' => ['nullable', 'string'],
            'ist_standard' => ['nullable', 'boolean'],
        ]);

        return [
            'name' => trim((string) $request->input('name')),
            'html' => (string) $request->input('html'),
            'text' => trim((string) $request->input('text')) ?: null,
        ];
    }
}
