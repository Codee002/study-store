<?php

use App\Models\Product;
use App\Models\Warehouse;
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
            // Foreign keys
            $table->foreignIdFor(Warehouse::class)->constrained()->onDelete('cascade')->after('id');
            $table->foreignIdFor(Product::class)->constrained()->onDelete('cascade')->after('warehouse_id');

            $table->integer('quantity')->after('product_id');
            $table->enum('status', ['actived', 'disabled'])->default('disabled')->after('quantity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('warehouse_details', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->dropForeign(['product_id']);
            $table->dropColumn(['warehouse_id', 'product_id', 'quantity', 'status']);
        });
    }
};
