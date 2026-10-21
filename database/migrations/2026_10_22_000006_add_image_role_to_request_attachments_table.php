<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rolle eines Bild-Anhangs (SUN-RQ, Ticket #11).
 *
 * Die Diff-Komponente zeigt Bild-Anhaenge als Thumbnail und laesst Enes je
 * Bild entscheiden, ob es Logo oder Galeriebild wird. Diese Entscheidung ist
 * Teil des Vorgangs und muss ihn ueberleben — ein Haken, der nur im Livewire-
 * Zustand steht, ist nach einem Seitenwechsel weg und die Uebernahme (#7/#9)
 * wuesste nicht, wohin das Bild gehoert.
 *
 * null = noch nicht entschieden bzw. kein Bild (Rohmail, PDF).
 * Werte: App\ProfileRequests\Models\RequestAttachment::ROLE_*.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('request_attachments', function (Blueprint $table) {
            $table->string('image_role', 16)->nullable()->after('is_raw_mail');
        });
    }

    public function down(): void
    {
        Schema::table('request_attachments', function (Blueprint $table) {
            $table->dropColumn('image_role');
        });
    }
};
