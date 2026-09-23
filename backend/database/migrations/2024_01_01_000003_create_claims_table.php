<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['mine', 'steal', 'grab']);
            $table->unsignedSmallInteger('position')->default(1);
            $table->enum('status', [
                'waiting',
                'active',
                'expired',
                'completed',
                'overridden',
                'cancelled',
            ])->default('waiting');
            $table->unsignedBigInteger('amount');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            // Never two active Mine or active Steal for same product
            $table->index(['product_id', 'type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claims');
    }
};
