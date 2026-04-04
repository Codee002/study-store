<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->enum('type', ['order', 'receipt', 'evaluate', 'evaluate-reply', 'account-locked'])
                ->default('order')
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->enum('type', ['order', 'receipt', 'evaluate', 'evaluate-reply'])
                ->default('order')
                ->change();
        });
    }
};
