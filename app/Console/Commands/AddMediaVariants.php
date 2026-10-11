<?php

namespace App\Console\Commands;

use App\Domain\Media\MediaUploader;
use App\Models\Media;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('media:variants {--usage= : Only images of this usage}')]
#[Description('Create image sizes that config/media.php defines but existing images are missing')]
class AddMediaVariants extends Command
{
    public function handle(MediaUploader $uploader): int
    {
        $created = 0;

        Media::query()
            ->when($this->option('usage'), fn ($q, $usage) => $q->where('usage', $usage))
            ->lazyById()
            ->each(function (Media $media) use ($uploader, &$created) {
                if ($names = $uploader->addMissingVariants($media)) {
                    $created++;
                    $this->line("#{$media->id} {$media->original_name}: ".implode(', ', $names));
                }
            });

        $this->info($created ? "Updated {$created} image(s)." : 'All images already have every size.');

        return self::SUCCESS;
    }
}
