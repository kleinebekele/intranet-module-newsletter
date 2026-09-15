<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Intranet\Modules\Newsletter\NewsletterServiceProvider;

/**
 * Der Newsletter-Rahmen zieht aus Verwaltung → Mailvorlagen ins Modul um.
 *
 * Bis v1.7 lag er dort als `_rahmen_newsletter`. Er wird als Vorlage
 * „Newsletter-Rahmen" nach `newsletter_vorlagen` übernommen – in der dort
 * angepassten Fassung, sonst als mitgelieferter Rahmen des Moduls. Nicht als
 * Standard: Ohne gewählte Vorlage gilt der allgemeine Rahmen des Intranets.
 * Die alte Zeile in `mail_vorlagen` bleibt stehen (sie stört nicht und ist
 * der Beleg, was übernommen wurde).
 */
return new class extends Migration
{
    private const NAME = 'Newsletter-Rahmen';

    public function up(): void
    {
        if (! Schema::hasTable('newsletter_vorlagen')) {
            return;
        }

        if (DB::table('newsletter_vorlagen')->where('name', self::NAME)->exists()) {
            return;
        }

        $html = NewsletterServiceProvider::RAHMEN_HTML;
        $text = NewsletterServiceProvider::RAHMEN_TEXT;

        if (Schema::hasTable('mail_vorlagen')) {
            $alt = DB::table('mail_vorlagen')->where('schluessel', NewsletterServiceProvider::RAHMEN)->first();

            if ($alt !== null && trim((string) $alt->html) !== '') {
                $html = (string) $alt->html;
                $text = trim((string) ($alt->text ?? '')) ?: $text;
            }
        }

        DB::table('newsletter_vorlagen')->insert([
            'name' => self::NAME,
            'html' => $html,
            'text' => $text,
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
