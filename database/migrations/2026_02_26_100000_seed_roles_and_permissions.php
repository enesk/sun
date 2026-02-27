<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Ensure roles and permissions exist in the central database.
     * Uses findOrCreate internally — safe to run multiple times.
     */
    public function up(): void
    {
        $seeder = new \Database\Seeders\RolesAndPermissionsSeeder();
        $seeder->run();
    }

    /**
     * Roles/permissions are not removed on rollback to prevent data loss.
     */
    public function down(): void
    {
        // Intentionally empty — removing roles could break existing user assignments.
    }
};
