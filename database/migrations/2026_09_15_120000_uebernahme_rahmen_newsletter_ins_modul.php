<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Der Newsletter-Rahmen zieht aus Verwaltung → Mailvorlagen ins Modul um.
 *
 * Bis v1.7 lag er dort als `_rahmen_newsletter`. Hat jemand ihn angepasst
 * (Zeile in `mail_vorlagen`), wandert diese Fassung als normale Vorlage nach
 * `newsletter_vorlagen` – nicht als Standard: Ohne gewählte Vorlage gilt ab
 * jetzt der allgemeine Rahmen des Intranets. Die alte Zeile bleibt stehen
 * (sie stört nicht und ist der Beleg, was übernommen wurde).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mail_vorlagen') || ! Schema::hasTable('newsletter_vorlagen')) {
            return;
        }

        $alt = DB::table('mail_vorlagen')->where('schluessel', '_rahmen_newsletter')->first();

        if ($alt === null || trim((string) $alt->html) === '') {
            return;
        }

        if (DB::table('newsletter_vorlagen')->where('name', 'Newsletter-Rahmen (aus der Verwaltung)')->exists()) {
            return;
        }

        DB::table('newsletter_vorlagen')->insert([
            'name' => 'Newsletter-Rahmen (aus der Verwaltung)',
            'html' => (string) $alt->html,
            'text' => trim((string) ($alt->text ?? '')) ?: null,
            'ist_standard' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Übernommene Daten bleiben – ein Rückbau löscht keine Vorlagen.
    }
};
