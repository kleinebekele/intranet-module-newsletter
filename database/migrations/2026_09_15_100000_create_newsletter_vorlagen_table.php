<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Eigene Rahmen (Mailvorlagen) des Newsletter-Moduls.
 *
 * Bisher ließ sich der Newsletter-Rahmen nur unter Verwaltung → Mailvorlagen
 * pflegen – also nur von Admins. Hier legt die Redaktion ihre eigenen Rahmen
 * an, ohne Zugang zum Adminbereich. Ist keine Vorlage angelegt oder an einer
 * Ausgabe keine gewählt, gilt weiterhin der Rahmen aus der Verwaltung.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('newsletter_vorlagen')) {
            return;
        }

        Schema::create('newsletter_vorlagen', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->longText('html');
            $table->longText('text')->nullable();
            // Genau eine Vorlage darf Standard sein: Sie wird bei neuen Ausgaben
            // vorbelegt. Ohne Standard startet eine neue Ausgabe mit dem Rahmen
            // aus der Verwaltung.
            $table->boolean('ist_standard')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_vorlagen');
    }
};
