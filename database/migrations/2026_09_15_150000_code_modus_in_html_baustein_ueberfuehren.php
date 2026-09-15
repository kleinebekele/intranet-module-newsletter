<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Der Reiter „Eigener Code" ist weg – eigener Code ist jetzt ein Baustein
 * („HTML-Code") im Baukasten. Ausgaben im alten Code-Modus bekommen ihr HTML
 * als einzigen Baustein; `mit_rahmen` bleibt, wie es war (false = Rahmen-Wahl
 * „keine"). Die Spalten `modus`/`html`/`text` bleiben stehen, werden aber
 * nicht mehr beschrieben.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('newsletter_kampagnen', 'modus')) {
            return;
        }

        DB::table('newsletter_kampagnen')
            ->where('modus', 'code')
            ->orderBy('id')
            ->each(function (object $zeile): void {
                $html = trim((string) ($zeile->html ?? ''));

                DB::table('newsletter_kampagnen')->where('id', $zeile->id)->update([
                    'bausteine' => json_encode($html === '' ? [] : [['typ' => 'html', 'html' => $html]], JSON_UNESCAPED_UNICODE),
                    'modus' => 'bausteine',
                ]);
            });
    }

    public function down(): void
    {
        // Nicht umkehrbar ohne Informationsverlust – die Bausteine bleiben.
    }
};
