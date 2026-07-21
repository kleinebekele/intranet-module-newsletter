<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zweiter Weg, eine Ausgabe zu schreiben: eigener HTML-Code statt Bausteine.
 *
 * `modus` entscheidet, welcher gilt. Beide Fassungen bleiben nebeneinander
 * gespeichert – wer zwischen den Reitern hin- und herwechselt, soll seine
 * Arbeit nicht verlieren.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('newsletter_kampagnen', function (Blueprint $table) {
            $table->string('modus', 20)->default('bausteine')->after('betreff');
            $table->longText('html')->nullable()->after('bausteine');
            $table->longText('text')->nullable()->after('html');
        });
    }

    public function down(): void
    {
        Schema::table('newsletter_kampagnen', function (Blueprint $table) {
            $table->dropColumn(['modus', 'html', 'text']);
        });
    }
};
