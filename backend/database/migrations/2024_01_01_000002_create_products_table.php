<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category')->nullable();
            $table->string('brand')->nullable();
            $table->string('size')->nullable();
            $table->enum('condition', ['excellent', 'good', 'fair', 'poor'])->default('good');
            $table->string('image')->nullable();
            $table->unsignedBigInteger('mine_price');   // stored in centavos / whole pesos
            $table->unsignedBigInteger('steal_price');
            $table->unsignedBigInteger('grab_price');
            $table->enum('status', [
                'available',
                'mine_pending',
                'steal_pending',
                'grab_pending',
                'sold',
            ])->default('available');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
