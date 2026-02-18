<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // companies: Jede öffentliche Query filtert is_active
        Schema::table('companies', function (Blueprint $table) {
            $table->index('is_active', 'idx_companies_active');
            $table->index(['is_active', 'is_premium'], 'idx_companies_active_premium');
            $table->index(['is_active', 'city_id'], 'idx_companies_active_city');
            $table->index(['is_active', 'created_at'], 'idx_companies_active_created');
        });

        // category_company: Reverse-Index für $company->categories Lookups
        Schema::table('category_company', function (Blueprint $table) {
            $table->index(['company_id', 'category_id'], 'idx_catco_company_category');
        });

        // reviews: Composite für approvedReviews() mit moderation_status
        Schema::table('reviews', function (Blueprint $table) {
            $table->index(
                ['company_id', 'moderation_status', 'created_at'],
                'idx_reviews_company_modstatus_created'
            );
        });

        // categories: parent_id + sort_order für roots()->ordered()
        Schema::table('categories', function (Blueprint $table) {
            $table->index(['parent_id', 'sort_order'], 'idx_categories_parent_sort');
        });

        // cities: zipcode für PLZ-Suche, name für City-Autocomplete
        Schema::table('cities', function (Blueprint $table) {
            $table->index('zipcode', 'idx_cities_zipcode');
            $table->index('name', 'idx_cities_name');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropIndex('idx_companies_active');
            $table->dropIndex('idx_companies_active_premium');
            $table->dropIndex('idx_companies_active_city');
            $table->dropIndex('idx_companies_active_created');
        });

        Schema::table('category_company', function (Blueprint $table) {
            $table->dropIndex('idx_catco_company_category');
        });

        Schema::table('reviews', function (Blueprint $table) {
            $table->dropIndex('idx_reviews_company_modstatus_created');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex('idx_categories_parent_sort');
        });

        Schema::table('cities', function (Blueprint $table) {
            $table->dropIndex('idx_cities_zipcode');
            $table->dropIndex('idx_cities_name');
        });
    }
};
