<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bis v1.13 wies sich der Newsletter beim Versand nicht als Modul aus – seine
 * Mails standen im Maillog unter Modul „Core", Auslöser „Newsletter". Das
 * wird hier für die bestehenden Zeilen nachgetragen, ebenso ein unter
 * „Core / Newsletter" hinterlegter Absender (Verwaltung → Absender je Auslöser),
 * damit er nach der Umstellung weiter greift.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mail_outbox') && Schema::hasColumn('mail_outbox', 'modul')) {
            DB::table('mail_outbox')
                ->where('quelle', 'Newsletter')
                ->where(fn ($q) => $q->whereNull('modul')->orWhere('modul', 'Core'))
                ->update(['modul' => 'Newsletter']);
        }

        if (Schema::hasTable('mail_absender')) {
            $schonDa = DB::table('mail_absender')
                ->where('modul', 'Newsletter')->where('ausloeser', 'Newsletter')->exists();

            if (! $schonDa) {
                DB::table('mail_absender')
                    ->where('modul', 'Core')->where('ausloeser', 'Newsletter')
                    ->update(['modul' => 'Newsletter']);
            }
        }
    }

    public function down(): void
    {
        // Bewusst leer: die alte Zuordnung „Core" war falsch.
    }
};
