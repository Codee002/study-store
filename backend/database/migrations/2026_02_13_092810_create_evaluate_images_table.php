<?php

use App\Models\Evaluate;
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
        Schema::create('evaluate_medias', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Evaluate::class)->constrained()->onDelete('cascade');
            $table->enum('type', ['image', 'video']);
            $table->string("url");
            $table->string("public_id");
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evaluate_medias');
    }
};
