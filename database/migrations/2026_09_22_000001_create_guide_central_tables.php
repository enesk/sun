<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central-Tabellen des Ratgebersystems (docs/guide-system.md, §4).
 *
 * Themenlisten und ihre Vorlagen steuert der Betreiber mandantenuebergreifend;
 * die Alarme liest die Uebersicht des Dashboards fuer alle Portale in einer
 * Abfrage. `prompt_templates` und `llm_usage_logs` stammen aus der
 * Content-Pipeline (2026_09_10_000002) und werden weiterverwendet. Sie werden
 * hier nur angelegt, falls sie fehlen, und in down() nie entfernt, weil die
 * alte Pipeline bis #19 darauf schreibt.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Importierte Themenlisten (#6).
        Schema::create('guide_topic_lists', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('branch', 120)->nullable();       // Branche, fuer die die Liste gedacht ist
            $table->string('source', 32)->nullable();        // csv|xlsx|paste
            $table->text('description')->nullable();
            $table->unsignedBigInteger('created_by')->nullable(); // users.id
            $table->timestamps();

            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index('branch');
        });

        // Themen-Vorlagen einer Liste; Platzhalter wie {branche} loest der Import je Tenant auf.
        Schema::create('guide_topic_list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guide_topic_list_id')
                ->constrained('guide_topic_lists')
                ->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->string('question', 500);
            $table->string('category_name')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedSmallInteger('priority')->default(0);
            $table->unsignedSmallInteger('refresh_interval_days')->nullable(); // null = config('guide.schedule.probe_interval_days')
            $table->timestamps();

            $table->index(['guide_topic_list_id', 'position']);
        });

        if (! Schema::hasTable('prompt_templates')) {
            Schema::create('prompt_templates', function (Blueprint $table) {
                $table->id();
                $table->string('key', 64);
                $table->unsignedBigInteger('tenant_id')->nullable(); // null = global
                $table->unsignedSmallInteger('version')->default(1);
                $table->string('name');
                $table->longText('system_prompt')->nullable();
                $table->longText('user_prompt');
                $table->json('variables_json')->nullable();
                $table->json('output_schema_json')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('created_by')->nullable(); // users.id
                $table->timestamps();

                $table->foreign('tenant_id')
                    ->references('id')
                    ->on('tenants')
                    ->cascadeOnDelete();

                $table->unique(['key', 'tenant_id', 'version']);
                $table->index(['key', 'is_active']);
            });
        }

        if (! Schema::hasTable('llm_usage_logs')) {
            Schema::create('llm_usage_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->string('provider', 32);
                $table->string('model', 64)->nullable();
                $table->string('operation', 64);                    // Praefix guide. fuer das Ratgebersystem
                $table->string('reference_type', 64)->nullable();   // guide_run
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->unsignedInteger('input_tokens')->default(0);
                $table->unsignedInteger('output_tokens')->default(0);
                $table->unsignedInteger('cache_write_tokens')->default(0);
                $table->unsignedInteger('cache_read_tokens')->default(0);
                $table->unsignedInteger('requests')->default(1);
                $table->decimal('cost_usd', 10, 6)->default(0);
                $table->unsignedInteger('duration_ms')->nullable();
                $table->boolean('was_successful')->default(true);
                $table->string('error_message', 500)->nullable();
                $table->timestamps();

                $table->foreign('tenant_id')
                    ->references('id')
                    ->on('tenants')
                    ->cascadeOnDelete();

                $table->index(['created_at', 'provider']);
                $table->index(['tenant_id', 'created_at']);
            });
        }

        // Alarme des Tageslaufs (#13). dedupe_key wie bei content_alerts: derselbe
        // Missstand zaehlt occurrences hoch, statt neue Zeilen anzulegen.
        Schema::create('guide_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('dedupe_key', 191)->unique();
            $table->unsignedBigInteger('tenant_id')->nullable();          // null = netzwerkweit
            $table->unsignedBigInteger('guide_topic_id')->nullable();     // guide_topics.id in der Tenant-DB
            $table->unsignedBigInteger('guide_topic_run_id')->nullable(); // guide_topic_runs.id in der Tenant-DB
            $table->string('key', 64);                                    // App\Guide\Models\Central\GuideAlert::KEY_*
            $table->string('level', 16)->default('warning');              // critical|warning|info
            $table->text('message');
            $table->json('context_json')->nullable();
            $table->date('for_date')->nullable();
            $table->string('status', 16)->default('open');                // open|resolved
            $table->unsignedInteger('occurrences')->default(1);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();

            $table->index(['status', 'level']);
            $table->index(['tenant_id', 'for_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guide_alerts');
        Schema::dropIfExists('guide_topic_list_items');
        Schema::dropIfExists('guide_topic_lists');
    }
};
