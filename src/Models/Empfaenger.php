<?php

namespace Intranet\Modules\Newsletter\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * „Diese Person soll diese Ausgabe bekommen" – und später: ob sie es wurde.
 *
 * `eingeliefert` heißt: Die Mail liegt im Ausgangskorb des Core. Ob sie von
 * dort tatsächlich rausging, weiß der Ausgangskorb; ihn hier nachzubilden
 * würde dieselbe Wahrheit an zwei Stellen führen.
 */
class Empfaenger extends Model
{
    public const WARTEND = 'wartend';

    public const EINGELIEFERT = 'eingeliefert';

    public const UEBERSPRUNGEN = 'uebersprungen';

    public const FEHLER = 'fehler';

    protected $table = 'newsletter_empfaenger';

    protected $fillable = ['kampagne_id', 'user_id', 'email', 'status', 'grund'];

    protected $casts = ['eingeliefert_am' => 'datetime'];

    public function kampagne(): BelongsTo
    {
        return $this->belongsTo(Kampagne::class, 'kampagne_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeWartend(Builder $query): Builder
    {
        return $query->where('status', self::WARTEND);
    }

    public function abschliessen(string $status, ?string $grund = null): void
    {
        $this->forceFill([
            'status' => $status,
            'grund' => $grund,
            'eingeliefert_am' => $status === self::EINGELIEFERT ? now() : null,
        ])->save();
    }
}
