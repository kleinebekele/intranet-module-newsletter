<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Welche eigene Vorlage (newsletter_vorlagen) rahmt diese Ausgabe? NULL = der
 * Rahmen aus Verwaltung → Mailvorlagen. Bewusst ohne Fremdschlüssel: Eine
 * gelöschte Vorlage darf die Ausgabe nicht mitreißen – sie fällt dann auf den
 * Rahmen der Verwaltung zurück.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('newsletter_kampagnen', function (Blueprint $table) {
            if (! Schema::hasColumn('newsletter_kampagnen', 'vorlage_id')) {
                $table->unsignedBigInteger('vorlage_id')->nullable()->after('mit_rahmen');
            }
        });
    }

    public function down(): void
    {
        Schema::table('newsletter_kampagnen', function (Blueprint $table) {
            $table->dropColumn('vorlage_id');
        });
    }
};
