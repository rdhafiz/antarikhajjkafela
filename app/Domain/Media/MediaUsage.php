<?php

namespace App\Domain\Media;

use App\Domain\Settings\Settings;
use App\Domain\Travel\PackageSchema;
use App\Models\GalleryAlbum;
use App\Models\GalleryItem;
use App\Models\Media;
use App\Models\Package;
use App\Models\Page;
use App\Models\PageSection;
use App\Models\Post;
use App\Models\Review;
use App\Models\Service;
use App\PageBuilder\ComponentRegistry;
use App\PageBuilder\ContentBlocks;
use App\PageBuilder\ContentProcessor;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Where an image is used: settings (logo, icons, banners), the records that
 * point at it (service, package, post, review, album, gallery photo) and the
 * content that holds it (page sections, package sections, post blocks, SEO
 * share images). Media in use cannot be deleted, or those places would show
 * a broken image.
 */
class MediaUsage
{
    public function __construct(
        private readonly Settings $settings,
        private readonly ContentProcessor $content,
        private readonly ContentBlocks $blocks,
        private readonly ComponentRegistry $components,
    ) {}

    /**
     * @return list<string> e.g. 'Settings → Branding: Website logo', 'Album "Umrah 2025" (cover)'
     */
    public function of(Media $media): array
    {
        $id = (int) $media->getKey();
        $used = [];

        foreach (config('settings.groups', []) as $group => $definition) {
            foreach ($definition['fields'] as $key => $field) {
                if (($field['type'] ?? null) === 'media' && (int) $this->settings->get("{$group}.{$key}") === $id) {
                    $used[] = "Settings → {$definition['label']}: {$field['label']}";
                }
            }
        }

        $name = fn (Model $model, string $attribute) => '"'.(method_exists($model, 'getTranslation') ? ($model->getTranslation($attribute, 'en', false) ?: collect($model->getTranslations($attribute))->first()) : $model->{$attribute}).'"';
        $seo = fn (Model $model) => (int) ($model->seo['image'] ?? 0) === $id;

        foreach (Service::all() as $service) {
            if ((int) $service->image_id === $id) {
                $used[] = "Service {$name($service, 'name')} (image)";
            }
            if ($seo($service)) {
                $used[] = "Service {$name($service, 'name')} (share image)";
            }
        }

        foreach (Package::all() as $package) {
            if ((int) $package->banner_id === $id) {
                $used[] = "Package {$name($package, 'name')} (banner)";
            }
            if (in_array($id, $this->content->mediaIds(PackageSchema::sections(), ['sections' => $package->sections]), true)) {
                $used[] = "Package {$name($package, 'name')} (page section)";
            }
            if ($seo($package)) {
                $used[] = "Package {$name($package, 'name')} (share image)";
            }
        }

        foreach (Post::all() as $post) {
            if (in_array($id, [(int) $post->banner_id, (int) $post->author_image_id], true)) {
                $used[] = "Post {$name($post, 'title')} (".((int) $post->banner_id === $id ? 'banner' : 'author photo').')';
            }
            if (in_array($id, $this->blocks->mediaIds($post->blocks), true)) {
                $used[] = "Post {$name($post, 'title')} (content)";
            }
            if ($seo($post)) {
                $used[] = "Post {$name($post, 'title')} (share image)";
            }
        }

        foreach (Review::where('photo_id', $id)->get() as $review) {
            $used[] = "Review by {$name($review, 'name')} (photo)";
        }

        foreach (GalleryAlbum::where('cover_id', $id)->get() as $album) {
            $used[] = "Album {$name($album, 'title')} (cover)";
        }

        foreach (GalleryItem::with('album')->where('media_id', $id)->get() as $item) {
            $used[] = 'Album '.($item->album ? $name($item->album, 'title') : '').' (photo)';
        }

        foreach (Page::with('sections')->get() as $page) {
            if ($seo($page)) {
                $used[] = "Page {$name($page, 'title')} (share image)";
            }

            foreach ($page->sections as $section) {
                if ($this->sectionUses($section, $id)) {
                    $used[] = "Page {$name($page, 'title')} (section)";
                    break;
                }
            }
        }

        return array_values(array_unique($used));
    }

    private function sectionUses(PageSection $section, int $id): bool
    {
        if ((int) ($section->style['background_image'] ?? 0) === $id) {
            return true;
        }

        try {
            return in_array($id, $this->content->mediaIds($this->components->get($section->component)->fields(), $section->content), true);
        } catch (Throwable) {
            return false;
        }
    }
}
