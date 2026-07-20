<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Eine Zeile je Person und Ausgabe.
 *
 * Damit ist der Versand nachvollziehbar („hat Familie X den Newsletter
 * bekommen?") und wiederaufnehmbar: Bricht ein Lauf ab, macht der nächste
 * genau dort weiter, wo er aufgehört hat.
 *
 * Die Adresse wird mitgeschrieben, statt sie später über den Benutzer zu
 * suchen – sie kann sich ändern, und dann stimmte die Auskunft nicht mehr.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_empfaenger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kampagne_id')->constrained('newsletter_kampagnen')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email');
            $table->string('status', 20)->default('wartend');
            $table->string('grund')->nullable();
            $table->timestamp('eingeliefert_am')->nullable();
            $table->timestamps();

            // Niemand bekommt dieselbe Ausgabe zweimal.
            $table->unique(['kampagne_id', 'user_id']);
            // Die Abfrage des Versand-Commands: „was ist hier noch offen?"
            $table->index(['kampagne_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_empfaenger');
    }
};
