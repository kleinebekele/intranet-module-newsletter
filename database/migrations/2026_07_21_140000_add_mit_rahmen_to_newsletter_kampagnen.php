<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Im Code-Modus wählbar: Nutzt die Ausgabe den Newsletter-Rahmen (Kopf, Fuß,
 * Anrede) – oder ist das eingegebene HTML die KOMPLETTE Mail?
 *
 * Nur im Code-Modus von Bedeutung. Der Baukasten liefert immer Fragmente und
 * braucht den Rahmen deshalb zwingend; für ihn bleibt das Feld auf `true`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('newsletter_kampagnen', function (Blueprint $table) {
            $table->boolean('mit_rahmen')->default(true)->after('modus');
        });
    }

    public function down(): void
    {
        Schema::table('newsletter_kampagnen', function (Blueprint $table) {
            $table->dropColumn('mit_rahmen');
        });
    }
};
