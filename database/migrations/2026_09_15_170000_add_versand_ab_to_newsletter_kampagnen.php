<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Frühester Versandzeitpunkt einer freigegebenen Ausgabe. NULL = sofort.
 * Der Versand-Command lässt Ausgaben mit einem Termin in der Zukunft liegen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('newsletter_kampagnen', function (Blueprint $table) {
            if (! Schema::hasColumn('newsletter_kampagnen', 'versand_ab')) {
                $table->dateTime('versand_ab')->nullable()->after('freigegeben_am');
            }
        });
    }

    public function down(): void
    {
        Schema::table('newsletter_kampagnen', function (Blueprint $table) {
            $table->dropColumn('versand_ab');
        });
    }
};
