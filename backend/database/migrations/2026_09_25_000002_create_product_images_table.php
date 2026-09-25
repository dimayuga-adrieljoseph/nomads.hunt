<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('image_path');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index(['product_id', 'sort_order']);
        });

        // Keep the legacy column and files intact as a rollback safety net. The
        // relationship is the runtime source of truth from this migration onward.
        DB::table('products')
            ->select('id', 'image')
            ->whereNotNull('image')
            ->where('image', '!=', '')
            ->orderBy('id')
            ->each(function (object $product): void {
                DB::table('product_images')->insert([
                    'product_id' => $product->id,
                    'image_path' => str_starts_with($product->image, 'products/')
                        ? $product->image
                        : 'products/'.$product->image,
                    'sort_order' => 0,
                    'is_primary' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};
