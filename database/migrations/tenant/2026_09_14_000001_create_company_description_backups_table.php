<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sicherung vor der Bereinigung von KI-Footprints (#2), Grundlage fuer
 * seo:restore-ai-footprints.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_description_backups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('column_name', 64)->default('description')
                ->comment('Bereinigte Spalte in companies');
            $table->mediumText('original_description');
            $table->mediumText('cleaned_description');
            $table->string('matched_pattern')->comment('Kommagetrennte Musternamen aus config/seo.php');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('restored_at')->nullable();

            // Eigene Namen: der erzeugte Name waere laenger als MySQLs 64 Zeichen.
            $table->index(['company_id', 'column_name', 'restored_at'], 'cdb_company_column_restored_index');
            $table->index('created_at', 'cdb_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_description_backups');
    }
};
