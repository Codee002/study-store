<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('conversations')) {
            DB::statement("ALTER TABLE `conversations` MODIFY `type` ENUM('private','group','chatbox') NULL");
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('conversations')) {
            DB::statement("ALTER TABLE `conversations` MODIFY `type` ENUM('private','group') NULL");
        }
    }
};
