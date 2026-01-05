<?php

use App\Models\Supplier;
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
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();

            // Foreign keys
            $table->foreignIdFor(Warehouse::class)->constrained()->onDelete('cascade');
            $table->foreignIdFor(Supplier::class)->constrained()->onDelete('cascade');

            $table->enum("status", ["pending", "completed", "canceled"])->default("pending");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};
