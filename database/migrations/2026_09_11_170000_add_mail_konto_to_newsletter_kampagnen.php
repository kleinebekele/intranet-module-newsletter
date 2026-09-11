<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Welcher SMTP-Absender (Core: Verwaltung → Maillog → SMTP-Absender) verschickt
 * diese Ausgabe? NULL = Standard-Mailer der Instanz. Bewusst ohne Fremdschlüssel:
 * Ein gelöschtes Konto darf die Ausgabe nicht mitreißen – sie fällt dann auf den
 * Standard zurück (der Versand-Command prüft das).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('newsletter_kampagnen', function (Blueprint $table) {
            if (! Schema::hasColumn('newsletter_kampagnen', 'mail_konto_id')) {
                $table->unsignedBigInteger('mail_konto_id')->nullable()->after('antwort_an');
            }
        });
    }

    public function down(): void
    {
        Schema::table('newsletter_kampagnen', function (Blueprint $table) {
            $table->dropColumn('mail_konto_id');
        });
    }
};
