<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evaluates', function (Blueprint $table) {
            $table->text('reply')->nullable()->after('content');
        });
    }

    public function down(): void
    {
        Schema::table('evaluates', function (Blueprint $table) {
            $table->dropColumn('reply');
        });
    }
};
