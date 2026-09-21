<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant-Tabellen des Ratgebersystems (docs/guide-system.md, §4).
 *
 * Kategorien, Themen mit fester Gliederung, Laeufe, Fakten-Set, Quellen und
 * die Versionshistorie der Artikel. Die aktuelle Fassung eines Artikels steht
 * weiterhin in `posts`; `guide_article_details` erweitert sie 1:1 um die
 * Ratgeber-Felder, ohne `posts` selbst aufzublaehen.
 *
 * Status- und Moduswerte sind Strings mit den Werten aus App\Guide\Enums\*.
 * `guide_topics.list_item_id` zeigt auf guide_topic_list_items in der
 * Central-DB und hat deshalb keinen Fremdschluessel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guide_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->longText('intro_html')->nullable();
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();

            $table->index(['is_visible', 'position']);
        });

        Schema::create('guide_topics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('list_item_id')->nullable(); // central guide_topic_list_items.id
            $table->foreignId('guide_category_id')
                ->nullable()
                ->constrained('guide_categories')
                ->nullOnDelete();
            $table->string('question', 500);
            $table->string('slug')->unique();                       // nach Anlage unveraenderlich
            $table->text('notes')->nullable();
            $table->json('outline_json')->nullable();              // [{id, level, heading, children: [...]}]
            $table->timestamp('outline_locked_at')->nullable();
            $table->string('status', 20)->default('draft');        // App\Guide\Enums\TopicStatus
            $table->unsignedSmallInteger('priority')->default(0);
            $table->unsignedSmallInteger('refresh_interval_days')->nullable(); // null = config('guide.schedule.probe_interval_days')
            $table->foreignId('article_id')
                ->nullable()
                ->unique()
                ->constrained('posts')
                ->nullOnDelete();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('last_changed_at')->nullable();
            $table->timestamp('next_due_at')->nullable();
            $table->char('facts_hash', 40)->nullable();
            $table->unsignedSmallInteger('consecutive_failures')->default(0);
            $table->timestamps();

            $table->index('list_item_id');
            $table->index(['status', 'next_due_at']);
        });

        Schema::create('guide_topic_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guide_topic_id')
                ->constrained('guide_topics')
                ->cascadeOnDelete();
            $table->date('run_date');
            $table->string('status', 20)->default('queued');      // App\Guide\Enums\RunStatus
            $table->string('mode', 20);                            // App\Guide\Enums\RunMode
            $table->json('probe_json')->nullable();
            $table->json('research_json')->nullable();
            $table->json('changed_section_ids_json')->nullable();
            $table->text('change_summary')->nullable();
            $table->unsignedTinyInteger('quality_score')->nullable(); // 0-100
            $table->json('quality_report_json')->nullable();
            $table->decimal('cost_usd', 10, 6)->default(0);
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->unique(['guide_topic_id', 'run_date']);
            $table->index(['run_date', 'status']);
        });

        Schema::create('guide_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guide_topic_id')
                ->constrained('guide_topics')
                ->cascadeOnDelete();
            $table->string('url', 2048);
            $table->string('title')->nullable();
            $table->string('publisher')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('retrieved_at')->nullable();
            $table->string('trust_level', 16)->default('other');  // App\Guide\Enums\TrustLevel
            $table->timestamps();

            $table->index(['guide_topic_id', 'trust_level']);
        });

        Schema::create('guide_facts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guide_topic_id')
                ->constrained('guide_topics')
                ->cascadeOnDelete();
            $table->string('key', 128);
            $table->string('label');
            $table->string('value', 1000);
            $table->string('unit', 32)->nullable();
            $table->date('valid_from')->nullable();
            $table->foreignId('source_id')
                ->nullable()
                ->constrained('guide_sources')
                ->nullOnDelete();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->boolean('is_current')->default(true);
            $table->timestamps();

            $table->index(['guide_topic_id', 'is_current']);
            $table->index(['guide_topic_id', 'key']);
        });

        Schema::create('guide_article_versions', function (Blueprint $table) {
            $table->id();
            // Leer, solange die erste Fassung noch in der Pruef-Queue steht und
            // kein Post existiert; dann haelt guide_topic_id die Zuordnung.
            $table->foreignId('article_id')
                ->nullable()
                ->constrained('posts')
                ->nullOnDelete();
            $table->foreignId('guide_topic_id')
                ->nullable()
                ->constrained('guide_topics')
                ->nullOnDelete();
            $table->foreignId('guide_topic_run_id')
                ->nullable()
                ->constrained('guide_topic_runs')
                ->nullOnDelete();
            $table->unsignedInteger('version');
            $table->string('title');
            $table->longText('body_html');
            $table->json('faq_json')->nullable();
            $table->json('key_facts_json')->nullable();
            $table->text('change_summary')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['article_id', 'version']);
            $table->index(['guide_topic_id', 'version']);
        });

        Schema::create('guide_article_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')
                ->unique()
                ->constrained('posts')
                ->cascadeOnDelete();
            $table->text('short_answer')->nullable();
            $table->json('faq_json')->nullable();
            $table->json('key_facts_json')->nullable();
            $table->json('changelog_json')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('content_changed_at')->nullable();
            $table->timestamps();
        });

        // Genau eine Zeile je Tenant-DB (TenantGuideSettingSeeder).
        Schema::create('tenant_guide_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_active')->default(false);
            $table->boolean('is_ymyl')->default(false);
            $table->unsignedTinyInteger('auto_publish_threshold')->default(80); // YMYL: 90
            $table->decimal('daily_budget_usd', 8, 2)->nullable();             // null = config('guide.budget.daily_usd_per_tenant')
            $table->time('run_window_start')->nullable();                       // null = config('guide.run_window_start')
            $table->time('run_window_end')->nullable();
            $table->string('author_name')->nullable();
            $table->text('author_bio')->nullable();
            $table->string('branch', 120)->nullable();
            $table->string('branch_plural', 120)->nullable();
            $table->json('category_cta_mapping_json')->nullable();
            $table->json('source_whitelist_json')->nullable();
            $table->json('source_blacklist_json')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_guide_settings');
        Schema::dropIfExists('guide_article_details');
        Schema::dropIfExists('guide_article_versions');
        Schema::dropIfExists('guide_facts');
        Schema::dropIfExists('guide_sources');
        Schema::dropIfExists('guide_topic_runs');
        Schema::dropIfExists('guide_topics');
        Schema::dropIfExists('guide_categories');
    }
};
