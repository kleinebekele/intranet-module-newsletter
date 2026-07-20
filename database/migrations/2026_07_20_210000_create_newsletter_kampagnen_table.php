<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Eine Newsletter-Ausgabe.
 *
 * `titel` ist der interne Name in der Übersicht („Elternbrief Juli"),
 * `betreff` steht später im Postfach. Beides getrennt, weil die Übersicht
 * sonst lauter fast gleiche Betreffzeilen zeigt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_kampagnen', function (Blueprint $table) {
            $table->id();
            $table->string('titel');
            $table->string('betreff');

            // Die Bausteine der Ausgabe (Überschrift, Text, Bild, Knopf …) in
            // der Reihenfolge, in der sie in der Mail stehen.
            $table->json('bausteine')->nullable();

            // Rollen-Schlüssel, an die die Ausgabe geht – oder ['alle'].
            $table->json('zielgruppen')->nullable();

            $table->string('status', 20)->default('entwurf')->index();

            $table->foreignId('erstellt_von')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('freigegeben_von')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('freigegeben_am')->nullable();
            $table->timestamp('versand_beendet_am')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_kampagnen');
    }
};
