<?php

use App\Models\Message;
use App\Models\Product;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_products', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Message::class)->constrained()->cascadeOnDelete();
            $table->foreignIdFor(Product::class)->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['message_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_products');
    }
};
