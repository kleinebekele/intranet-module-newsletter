<?php

namespace Intranet\Modules\Newsletter\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ein eigener Rahmen des Newsletter-Moduls (Menüpunkt „Mailvorlagen").
 *
 * Der Rahmen um eine Ausgabe – Kopf, Fuß, Farben –, von der Redaktion selbst
 * gepflegt (bis v1.7 lag er als `_rahmen_newsletter` in der Verwaltung). Ohne
 * gewählte Vorlage gilt der allgemeine Rahmen des Intranets.
 * Die Platzhalter sind dieselben (`{{ inhalt }}`, `{{ titel }}`, `{{ logo }}`,
 * `{{ jahr }}`). Der Text der Ausgabe kommt weiterhin über die Vorlage
 * `newsletter` (Anrede, Abbinder) hinein.
 */
class Vorlage extends Model
{
    protected $table = 'newsletter_vorlagen';

    protected $fillable = ['name', 'html', 'text', 'ist_standard'];

    protected $casts = [
        'ist_standard' => 'boolean',
    ];

    /** Ausgaben, die diesen Rahmen gewählt haben (ohne Fremdschlüssel, s. Migration). */
    public function kampagnen(): HasMany
    {
        return $this->hasMany(Kampagne::class, 'vorlage_id');
    }

    /** Die Vorlage, die bei neuen Ausgaben vorbelegt wird – oder null. */
    public static function standard(): ?self
    {
        return static::where('ist_standard', true)->orderBy('id')->first();
    }

    /**
     * Diese Vorlage zum Standard machen. Es gibt höchstens einen – die anderen
     * verlieren die Markierung.
     */
    public function alsStandardSetzen(): void
    {
        static::where('id', '!=', $this->id)->where('ist_standard', true)->update(['ist_standard' => false]);
        $this->forceFill(['ist_standard' => true])->save();
    }
}
