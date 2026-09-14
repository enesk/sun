<?php

use App\Enums\ModerationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bewertungs-Moderation (#13).
 *
 * Die Spalte moderation_status gibt es seit 2026_02_15 als freien String. Sie
 * wird hier auf die vier Werte aus App\Enums\ModerationStatus festgezogen.
 *
 * - moderated_by war ein Freitext (mal Name, mal users.id). Der Altwert zieht
 *   nach moderated_by_name um, moderated_by wird zur users.id. Die users-Tabelle
 *   liegt in der Central-DB, deshalb kein Fremdschluessel.
 * - Bestand: alles, was bisher sichtbar war (is_approved = 1), ist approved.
 *   Echte offene Einsendungen bleiben pending — sie jetzt freizugeben, wuerde
 *   ungepruefte Fremdinhalte veroeffentlichen. Unbekannte Werte werden
 *   needs_review (fail-closed).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->renameColumn('moderated_by', 'moderated_by_name');
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->string('moderation_reason')->nullable()->after('moderation_status');
            $table->timestamp('moderated_at')->nullable()->after('moderation_note');
            $table->unsignedBigInteger('moderated_by')->nullable()->after('moderated_at')
                ->comment('users.id in der Central-DB, daher kein Fremdschluessel');
        });

        $values = ModerationStatus::values();

        DB::table('reviews')
            ->where('is_approved', true)
            ->where(fn ($query) => $query->whereNull('moderation_status')->orWhere('moderation_status', '!=', ModerationStatus::Approved->value))
            ->update(['moderation_status' => ModerationStatus::Approved->value]);

        DB::table('reviews')
            ->where(fn ($query) => $query->whereNull('moderation_status')->orWhereNotIn('moderation_status', $values))
            ->update([
                'moderation_status' => ModerationStatus::NeedsReview->value,
                'moderation_reason' => 'Unbekannter Altstatus',
            ]);

        DB::table('reviews')
            ->whereIn('moderation_status', [ModerationStatus::Approved->value, ModerationStatus::Rejected->value])
            ->update(['moderated_at' => DB::raw('COALESCE(approved_at, updated_at)')]);

        DB::table('reviews')
            ->whereNotNull('moderated_by_name')
            ->distinct()
            ->pluck('moderated_by_name')
            ->filter(fn ($value): bool => ctype_digit((string) $value))
            ->each(fn ($value) => DB::table('reviews')
                ->where('moderated_by_name', $value)
                ->update(['moderated_by' => (int) $value, 'moderated_by_name' => null]));

        DB::table('reviews')->where('moderated_by_name', '')->update(['moderated_by_name' => null]);

        Schema::table('reviews', function (Blueprint $table) use ($values) {
            $table->enum('moderation_status', $values)->default(ModerationStatus::Pending->value)->change();
            $table->index(['company_id', 'moderation_status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'moderation_status', 'created_at']);
            $table->string('moderation_status')->default(ModerationStatus::Pending->value)->change();
            $table->dropColumn(['moderation_reason', 'moderated_at', 'moderated_by']);
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->renameColumn('moderated_by_name', 'moderated_by');
        });
    }
};
