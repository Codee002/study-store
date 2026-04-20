<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('warehouse_details', function (Blueprint $table) {
            $table->index(
                ['warehouse_id', 'product_id', 'color_id'],
                'warehouse_details_lookup_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('warehouse_details', function (Blueprint $table) {
            $table->dropIndex('warehouse_details_lookup_idx');
        });
    }
};
