<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Eigener Absender je Ausgabe: der angezeigte NAME (die Adresse bleibt die der
 * Instanz, sonst landet die Mail im Spam) und eine Antwort-an-Adresse, damit
 * Rückfragen nicht im Systempostfach enden. Beides leer = Instanz-Standard.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('newsletter_kampagnen', function (Blueprint $table) {
            if (! Schema::hasColumn('newsletter_kampagnen', 'absender_name')) {
                $table->string('absender_name')->nullable()->after('betreff');
            }
            if (! Schema::hasColumn('newsletter_kampagnen', 'antwort_an')) {
                $table->string('antwort_an')->nullable()->after('absender_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('newsletter_kampagnen', function (Blueprint $table) {
            $table->dropColumn(['absender_name', 'antwort_an']);
        });
    }
};
