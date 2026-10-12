<?php

namespace App\Domain\Media;

use App\Models\Media;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use InvalidArgumentException;

/**
 * Stores an uploaded image and generates its optimized WebP variants.
 *
 * The untouched original is kept on a private disk (so it can be reprocessed
 * and its EXIF data is never public); only the stripped WebP variants are
 * written to the public disk.
 */
class MediaUploader
{
    private ImageManager $images;

    public function __construct()
    {
        $this->images = ImageManager::usingDriver(GdDriver::class);
    }

    /**
     * @param  array<string, array<string, mixed>>|null  $overrides  per-variant options chosen in manual mode
     */
    public function upload(UploadedFile $file, string $usage, ?array $overrides = null, ?User $uploader = null): Media
    {
        $variants = $this->variantsFor($usage, $overrides);

        $image = $this->images->decodePath($file->getRealPath());
        $image->orient();

        $uuid = (string) Str::uuid();
        $directory = trim(config('media.directory'), '/').'/'.now()->format('Y/m').'/'.$uuid;
        $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension());

        Storage::disk(config('media.originals_disk', 'local'))
            ->putFileAs($directory, $file, "original.{$extension}");

        $generated = [];

        foreach ($variants as $name => $spec) {
            $generated[$name] = $this->writeVariant($image, $directory, (string) $name, $spec);
        }

        $generated['admin'] = $this->writeVariant($image, $directory, 'admin', config('media.admin_variant'));

        return Media::create([
            'uuid' => $uuid,
            'disk' => config('media.disk'),
            'directory' => $directory,
            'filename' => "original.{$extension}",
            'original_name' => Str::limit($file->getClientOriginalName(), 250, ''),
            'mime_type' => $file->getMimeType(),
            'extension' => $extension,
            'size' => $file->getSize(),
            'width' => $image->width(),
            'height' => $image->height(),
            'usage' => $usage,
            'alt' => [],
            'variants' => $generated,
            'uploaded_by' => $uploader?->id,
        ]);
    }

    /**
     * The variant specs of a usage, merged with any manual overrides.
     *
     * @param  array<string, array<string, mixed>>|null  $overrides
     * @return array<string, array<string, mixed>>
     */
    public function variantsFor(string $usage, ?array $overrides = null): array
    {
        $definition = config("media.usages.{$usage}");

        if ($definition === null) {
            throw new InvalidArgumentException("Unknown media usage [{$usage}].");
        }

        $variants = [];

        foreach ($definition['variants'] as $name => $spec) {
            $override = $overrides[$name] ?? [];

            $variants[$name] = [
                'width' => $override['width'] ?? $spec['width'],
                'height' => array_key_exists('height', $override) ? $override['height'] : $spec['height'],
                'fit' => $override['fit'] ?? $spec['fit'],
                'quality' => $override['quality'] ?? $spec['quality'] ?? config('media.default_quality'),
                'crop' => $override['crop'] ?? null,
                'format' => $spec['format'] ?? 'webp',
            ];
        }

        return $variants;
    }

    /**
     * @param  array<string, mixed>  $spec
     * @return array{path: string, width: int, height: int, size: int}
     */
    /**
     * Create the variants an image's usage defines but the image lacks (for
     * example after a new size is added to config/media.php). Existing
     * variants, including manually optimized ones, are left untouched.
     *
     * @return list<string> the variant names that were created
     */
    public function addMissingVariants(Media $media): array
    {
        $missing = array_diff_key($this->variantsFor($media->usage), $media->variants ?? []);
        $original = $media->directory.'/'.$media->filename;
        $disk = Storage::disk(config('media.originals_disk', 'local'));

        if ($missing === [] || ! $disk->exists($original)) {
            return [];
        }

        $image = $this->images->decodePath($disk->path($original));
        $image->orient();
        $variants = $media->variants ?? [];

        foreach ($missing as $name => $spec) {
            $variants[$name] = $this->writeVariant($image, $media->directory, (string) $name, $spec);
        }

        $media->update(['variants' => $variants]);

        return array_map('strval', array_keys($missing));
    }

    private function writeVariant(ImageInterface $source, string $directory, string $name, array $spec): array
    {
        $image = clone $source;

        if (! empty($spec['crop'])) {
            $crop = $this->clampCrop($spec['crop'], $image->width(), $image->height());
            $image->crop($crop['width'], $crop['height'], $crop['x'], $crop['y']);
        }

        $width = $spec['width'] ?? null;
        $height = $spec['height'] ?? null;

        if (($spec['fit'] ?? 'scale') === 'cover' && $width && $height) {
            $image->coverDown($width, $height);
        } elseif ($width || $height) {
            $image->scaleDown($width, $height);
        }

        // WebP by default; 'format' => 'jpg' for images other sites read (social share previews).
        $quality = (int) ($spec['quality'] ?? config('media.default_quality'));
        $jpeg = ($spec['format'] ?? 'webp') === 'jpg';
        $encoded = $image->encode($jpeg ? new JpegEncoder(quality: $quality, progressive: true, strip: true) : new WebpEncoder(quality: $quality, strip: true));

        $path = "{$directory}/{$name}.".($jpeg ? 'jpg' : 'webp');
        $contents = $encoded->toString();

        Storage::disk(config('media.disk'))->put($path, $contents, 'public');

        return [
            'path' => $path,
            'width' => $image->width(),
            'height' => $image->height(),
            'size' => strlen($contents),
        ];
    }

    /**
     * @param  array<string, mixed>  $crop
     * @return array{x: int, y: int, width: int, height: int}
     */
    private function clampCrop(array $crop, int $maxWidth, int $maxHeight): array
    {
        $x = max(0, min((int) ($crop['x'] ?? 0), $maxWidth - 1));
        $y = max(0, min((int) ($crop['y'] ?? 0), $maxHeight - 1));

        return [
            'x' => $x,
            'y' => $y,
            'width' => max(1, min((int) ($crop['width'] ?? $maxWidth), $maxWidth - $x)),
            'height' => max(1, min((int) ($crop['height'] ?? $maxHeight), $maxHeight - $y)),
        ];
    }
}
