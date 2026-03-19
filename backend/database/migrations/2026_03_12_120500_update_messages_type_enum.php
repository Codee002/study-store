<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Allow recalled state
        if (Schema::hasTable('messages')) {
            DB::statement("ALTER TABLE `messages` MODIFY `type` ENUM('text','media','recalled') NULL");
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('messages')) {
            DB::statement("ALTER TABLE `messages` MODIFY `type` ENUM('text','media') NULL");
        }
    }
};
