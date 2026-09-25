<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class ProductImageService
{
    private const DESCRIPTOR_PATTERN = '/^(existing|new):([1-9][0-9]*)$/';

    /**
     * @param  array<int, UploadedFile>  $files
     * @return array<int, string>
     */
    public function storeUploads(array $files): array
    {
        $paths = [];

        try {
            foreach (array_values($files) as $index => $file) {
                $filename = 'product_'.uniqid('', true).'.'.strtolower($file->extension());

                if (! $file->storeAs('products', $filename, 'public')) {
                    throw new RuntimeException('Unable to store product image.');
                }

                $paths[$index] = 'products/'.$filename;
            }

            return $paths;
        } catch (Throwable $exception) {
            $this->deleteFiles($paths);

            throw $exception;
        }
    }

    /**
     * Existing IDs omitted from $order are removed. New descriptors point into
     * $storedPaths, with "new:1" being the first newly uploaded file.
     *
     * @param  array<int, string>  $storedPaths
     * @param  array<int, string>|null  $requestedOrder  Null appends to the current gallery; an empty array clears it.
     * @return array<int, string> Paths to delete only after the DB commit.
     */
    public function sync(
        Product $product,
        array $storedPaths,
        ?array $requestedOrder = null,
        ?string $requestedPrimary = null,
    ): array {
        $currentImages = $product->images()->lockForUpdate()->get();
        $order = $this->normalizeOrder($requestedOrder, $currentImages, $storedPaths);
        $primary = $this->resolvePrimary($requestedPrimary, $order, $currentImages);

        if ($primary !== null) {
            $order = [$primary, ...array_values(array_filter(
                $order,
                fn (string $descriptor) => $descriptor !== $primary,
            ))];
        }

        $removedPaths = $currentImages
            ->reject(fn (ProductImage $image) => in_array('existing:'.$image->id, $order, true))
            ->pluck('image_path')
            ->all();

        $product->images()
            ->whereNotIn('id', $currentImages->pluck('id')->all())
            ->delete();

        foreach ($order as $sortOrder => $descriptor) {
            [$type, $value] = $this->parseDescriptor($descriptor);

            if ($type === 'existing') {
                $currentImages->firstWhere('id', $value)?->update([
                    'sort_order' => $sortOrder,
                    'is_primary' => $descriptor === $primary,
                ]);

                continue;
            }

            ProductImage::create([
                'product_id' => $product->id,
                'image_path' => $storedPaths[$value - 1],
                'sort_order' => $sortOrder,
                'is_primary' => $descriptor === $primary,
            ]);
        }

        // Transitional rollback mirror only; runtime reads use product_images.
        $product->update([
            'image' => $primary ? basename($this->pathForDescriptor($primary, $currentImages, $storedPaths)) : null,
        ]);

        return $removedPaths;
    }

    public function delete(Product $product, ProductImage $image): string
    {
        if ($image->product_id !== $product->id) {
            throw ValidationException::withMessages(['image' => 'Image does not belong to this product.']);
        }

        $path = $image->image_path;
        $wasPrimary = $image->is_primary;
        $image->delete();
        $remaining = $product->images()->lockForUpdate()->get();

        $remaining->each(function (ProductImage $remainingImage, int $index) use ($wasPrimary): void {
            $remainingImage->update([
                'sort_order' => $index,
                'is_primary' => $wasPrimary ? $index === 0 : false,
            ]);
        });

        if (! $wasPrimary && $remaining->isNotEmpty()) {
            $remaining->first()->update(['is_primary' => true]);
        }

        $primary = $remaining->firstWhere('is_primary', true);
        $product->update(['image' => $primary ? basename($primary->image_path) : null]);

        return $path;
    }

    /** @param array<int, string> $paths */
    public function deleteFiles(array $paths): void
    {
        if ($paths !== []) {
            Storage::disk('public')->delete(array_values(array_unique($paths)));
        }
    }

    /**
     * @param  array<int, string>|null  $requestedOrder
     * @param  Collection<int, ProductImage>  $currentImages
     * @param  array<int, string>  $storedPaths
     * @return array<int, string>
     */
    private function normalizeOrder(?array $requestedOrder, Collection $currentImages, array $storedPaths): array
    {
        if ($requestedOrder === null) {
            return array_merge(
                $currentImages->map(fn (ProductImage $image) => 'existing:'.$image->id)->all(),
                array_map(fn (int $index) => 'new:'.($index + 1), array_keys($storedPaths)),
            );
        }

        if ($requestedOrder === [] && $storedPaths !== []) {
            throw ValidationException::withMessages([
                'image_order' => 'Uploaded images must be included in the image order.',
            ]);
        }

        if ($requestedOrder === []) {
            return [];
        }

        $seen = [];
        $currentIds = $currentImages->pluck('id')->map(fn (int $id) => 'existing:'.$id);
        $newDescriptors = array_map(
            fn (int $index) => 'new:'.($index + 1),
            array_keys($storedPaths),
        );

        foreach ($requestedOrder as $descriptor) {
            [$type, $value] = $this->parseDescriptor($descriptor);
            $valid = $type === 'existing'
                ? $currentIds->contains($descriptor)
                : in_array($descriptor, $newDescriptors, true);

            if (! $valid || in_array($descriptor, $seen, true)) {
                throw ValidationException::withMessages([
                    'image_order' => 'The image order contains a duplicate or invalid image.',
                ]);
            }

            $seen[] = $descriptor;
        }

        if (count(array_diff($newDescriptors, $requestedOrder)) > 0) {
            throw ValidationException::withMessages([
                'image_order' => 'Every uploaded image must be included in the order.',
            ]);
        }

        return array_values($requestedOrder);
    }

    /**
     * @param  array<int, string>  $order
     * @param  Collection<int, ProductImage>  $currentImages
     */
    private function resolvePrimary(?string $requestedPrimary, array $order, Collection $currentImages): ?string
    {
        if ($order === []) {
            if ($requestedPrimary !== null) {
                throw ValidationException::withMessages([
                    'primary_image' => 'The primary image must be in the gallery.',
                ]);
            }

            return null;
        }

        if ($requestedPrimary === null) {
            $currentPrimary = $currentImages->firstWhere('is_primary', true);

            return $currentPrimary && in_array('existing:'.$currentPrimary->id, $order, true)
                ? 'existing:'.$currentPrimary->id
                : $order[0];
        }

        if (! in_array($requestedPrimary, $order, true)) {
            throw ValidationException::withMessages([
                'primary_image' => 'The primary image must be in the gallery.',
            ]);
        }

        return $requestedPrimary;
    }

    /** @return array{string, int} */
    private function parseDescriptor(string $descriptor): array
    {
        if (! preg_match(self::DESCRIPTOR_PATTERN, $descriptor, $matches)) {
            throw ValidationException::withMessages([
                'image_order' => 'The image order contains an invalid image.',
            ]);
        }

        return [$matches[1], (int) $matches[2]];
    }

    /**
     * @param  Collection<int, ProductImage>  $currentImages
     * @param  array<int, string>  $storedPaths
     */
    private function pathForDescriptor(
        string $descriptor,
        Collection $currentImages,
        array $storedPaths,
    ): string {
        [$type, $value] = $this->parseDescriptor($descriptor);

        return $type === 'existing'
            ? $currentImages->firstWhere('id', $value)?->image_path
            : $storedPaths[$value - 1];
    }
}
