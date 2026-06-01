<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE employees DROP CONSTRAINT IF EXISTS employees_status_check");
            DB::statement("ALTER TABLE employees ADD CONSTRAINT employees_status_check CHECK (status::text = ANY (ARRAY['active'::text, 'inactive'::text, 'on_leave'::text, 'terminated'::text]))");
        } else {
            DB::statement("ALTER TABLE employees MODIFY COLUMN status ENUM('active', 'inactive', 'on_leave', 'terminated') DEFAULT 'active'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE employees DROP CONSTRAINT IF EXISTS employees_status_check");
            DB::statement("ALTER TABLE employees ADD CONSTRAINT employees_status_check CHECK (status::text = ANY (ARRAY['active'::text, 'inactive'::text, 'terminated'::text]))");
        } else {
            DB::statement("ALTER TABLE employees MODIFY COLUMN status ENUM('active', 'inactive', 'terminated') DEFAULT 'active'");
        }
    }
};
