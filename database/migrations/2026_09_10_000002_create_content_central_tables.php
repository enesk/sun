<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central-Tabellen der Content-Pipeline (siehe docs/content-pipeline.md, §4).
 *
 * Alles, was der Betreiber tenantuebergreifend sieht und steuert: Redaktions-
 * Accounts des Content-Panels, Fingerprints zur Duplikatspruefung ueber alle
 * Mandanten, Prompt-Templates, Kosten-Logging und Provider-Zustand.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Redaktions-Accounts des Content-Panels (eigener Guard, #4).
        Schema::create('content_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('role', 32)->default('editor'); // App\Content\Enums\ContentRole: owner|editor
            $table->boolean('is_active')->default(true);
            $table->json('tenant_ids_json')->nullable();   // null = alle Mandanten
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('content_password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        // Fingerprints veroeffentlichter Artikel — tenantuebergreifende
        // Duplikats- und Kannibalisierungspruefung (#12).
        // MariaDB kennt keinen Vektor-Index: Embeddings liegen als JSON-Array,
        // die Cosine-Aehnlichkeit rechnet PHP ueber vorgefilterte Kandidaten
        // (gleicher Cluster bzw. SimHash-Hamming-Distanz <= 12).
        Schema::create('content_fingerprints', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('article_id');     // posts.id in der Tenant-DB
            $table->string('url', 2048);
            $table->unsignedBigInteger('simhash');        // 64 Bit, vorzeichenlos
            $table->json('embedding_json')->nullable();
            $table->string('primary_keyword');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();

            $table->index('simhash');
            $table->index('tenant_id');
            $table->unique(['tenant_id', 'article_id']);
            $table->index('primary_keyword');
        });

        // Versionierte Prompt-Templates und Branchen-Styleguides (#13).
        Schema::create('prompt_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key', 64);                    // outline, section, faq, meta, quality
            $table->unsignedBigInteger('tenant_id')->nullable(); // null = global
            $table->unsignedSmallInteger('version')->default(1);
            $table->string('name');
            $table->longText('system_prompt')->nullable();
            $table->longText('user_prompt');
            $table->json('variables_json')->nullable();
            $table->json('output_schema_json')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable(); // content_users.id
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->cascadeOnDelete();

            $table->foreign('created_by')
                ->references('id')
                ->on('content_users')
                ->nullOnDelete();

            $table->unique(['key', 'tenant_id', 'version']);
            $table->index(['key', 'is_active']);
        });

        // Kosten- und Token-Logging je Provider-Aufruf (#6).
        Schema::create('llm_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('provider', 32);               // anthropic, dataforseo, voyage, images
            $table->string('model', 64)->nullable();
            $table->string('operation', 64);              // outline, section, faq, meta, embedding, serp
            $table->string('reference_type', 64)->nullable(); // article_drafts, topic_candidates
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

        // Zustand je Provider fuer Circuit Breaker, Rate-Limits und Quellen-Monitor (#7).
        Schema::create('provider_states', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32)->unique();
            $table->string('status', 20)->default('ok');  // ok|degraded|open|paused|failed|disabled
            $table->unsignedSmallInteger('consecutive_failures')->default(0);
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_failure_at')->nullable();
            $table->timestamp('circuit_open_until')->nullable();
            $table->unsignedInteger('requests_today')->default(0);
            $table->decimal('cost_today_usd', 10, 4)->default(0);
            $table->date('counters_date')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->json('meta_json')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_states');
        Schema::dropIfExists('llm_usage_logs');
        Schema::dropIfExists('prompt_templates');
        Schema::dropIfExists('content_fingerprints');
        Schema::dropIfExists('content_password_reset_tokens');
        Schema::dropIfExists('content_users');
    }
};
