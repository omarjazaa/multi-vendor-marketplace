<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductImage;
use App\Repositories\Contracts\ProductImageRepositoryInterface;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProductImageService
{
    public function __construct(private readonly ProductImageRepositoryInterface $images) {}

    /**
     * Return the images of a product ordered from oldest to newest.
     *
     * @return Collection<int, ProductImage>
     */
    public function forProduct(Product $product): iterable
    {
        return $this->images->forProduct($product);
    }

    /**
     * Store every uploaded file and persist its product image record.
     *
     * The first image of a product automatically becomes the primary image so
     * that a product is never left without a representative picture.
     *
     * @param  array<int, UploadedFile>  $files
     * @return Collection<int, ProductImage>
     */
    public function store(Product $product, array $files): iterable
    {
        $files = array_values($files);

        $this->guardAgainstExceedingLimit($product, count($files));

        return DB::transaction(function () use ($product, $files): Collection {
            $stored = new Collection;
            $hasPrimary = $product->images()->where('is_primary', true)->exists();

            foreach ($files as $file) {
                $stored->push($this->images->create($product, [
                    'path' => $this->storeFile($product, $file),
                    'is_primary' => ! $hasPrimary,
                ]));

                $hasPrimary = true;
            }

            return $stored;
        });
    }

    /**
     * Remove an image from the product and from the media disk.
     *
     * @throws ModelNotFoundException when the image belongs to another product
     */
    public function delete(Product $product, ProductImage $image): void
    {
        if ((int) $image->product_id !== (int) $product->id) {
            throw (new ModelNotFoundException)->setModel(ProductImage::class, [$image->id]);
        }

        DB::transaction(function () use ($product, $image): void {
            $wasPrimary = $image->is_primary;

            Storage::disk($this->disk())->delete($image->path);
            $this->images->delete($image);

            if ($wasPrimary) {
                $this->promotePrimary($product);
            }
        });
    }

    /** Move a single upload into the product folder on the media disk. */
    private function storeFile(Product $product, UploadedFile $file): string
    {
        $disk = Storage::disk($this->disk());
        $directory = "products/{$product->id}";
        $name = $this->uniqueName($disk, $directory, $this->safeFileName($file->getClientOriginalName()));

        $disk->putFileAs($directory, $file, $name);

        return "{$directory}/{$name}";
    }

    /** Strip directory information and unsafe characters from a client file name. */
    private function safeFileName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = trim((string) preg_replace('/[^A-Za-z0-9._-]+/', '-', $name), '.-');

        return $name === '' ? 'image' : $name;
    }

    /** Derive a file name that does not overwrite an existing upload. */
    private function uniqueName(Filesystem $disk, string $directory, string $name): string
    {
        $candidate = $name;
        $suffix = 1;

        while ($disk->exists("{$directory}/{$candidate}")) {
            $extension = pathinfo($name, PATHINFO_EXTENSION);
            $base = pathinfo($name, PATHINFO_FILENAME);
            $candidate = $extension === '' ? "{$base}-".(++$suffix) : "{$base}-".(++$suffix).".{$extension}";
        }

        return $candidate;
    }

    /** Promote the oldest remaining image once the primary one is gone. */
    private function promotePrimary(Product $product): void
    {
        $next = $this->images->forProduct($product)->first();

        if ($next instanceof ProductImage) {
            $this->images->update($next, ['is_primary' => true]);
        }
    }

    /** Block uploads that would push the product past the configured limit. */
    private function guardAgainstExceedingLimit(Product $product, int $incoming): void
    {
        $limit = (int) config('marketplace.products.images.max_per_product');

        if ($product->images()->count() + $incoming > $limit) {
            throw ValidationException::withMessages([
                'images' => "A product may have at most {$limit} images.",
            ]);
        }
    }

    private function disk(): string
    {
        return (string) config('marketplace.products.images.disk');
    }
}
