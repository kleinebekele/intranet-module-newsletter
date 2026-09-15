<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persönliche Zielgruppen („manuelle Gruppen") je Benutzer.
 *
 * Wer eine Ausgabe anlegt, kann sich eigene Gruppen zusammenstellen: Rollen
 * und einzelne Kontakte einschließen, Rollen und Kontakte ausschließen. Die
 * Gruppe gehört ihrem Ersteller (nur er sieht und pflegt sie); in einer
 * Ausgabe steht sie als Zielgruppe `gruppe:<id>`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('newsletter_gruppen')) {
            return;
        }

        Schema::create('newsletter_gruppen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 120);
            // {rollen_ein: [], user_ein: [], rollen_aus: [], user_aus: []}
            $table->json('regeln');
            $table->timestamps();

            $table->index(['user_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_gruppen');
    }
};
