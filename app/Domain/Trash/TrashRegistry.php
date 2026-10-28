<?php

namespace App\Domain\Trash;

use App\Domain\Booking\BookingService;
use App\Domain\Media\MediaUsage;
use App\Models\Booking;
use App\Models\GalleryAlbum;
use App\Models\GalleryItem;
use App\Models\Inquiry;
use App\Models\Media;
use App\Models\Package;
use App\Models\Page;
use App\Models\Payment;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Review;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use App\Support\Money;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\PermissionRegistrar;

/**
 * What the Trash lists and how each kind of record comes back. Parts that
 * are removed while editing (page sections, menu items, package options)
 * stay in the database too, but are not listed: they return with a revert
 * of the edit, not on their own.
 */
class TrashRegistry
{
    /**
     * @return array<string, array{model: class-string<Model>, label: string, title: Closure, detail?: Closure, blocker?: Closure, with?: list<string>, link?: Closure, after?: Closure}>
     */
    public function types(): array
    {
        $t = fn (Model $m, string $key) => method_exists($m, 'getTranslation') ? ($m->getTranslation($key, 'en', false) ?: collect($m->getTranslations($key))->first()) : $m->{$key};

        return [
            'bookings' => [
                'model' => Booking::class,
                'label' => 'Booking',
                'title' => fn (Booking $b) => "{$b->reference} · {$b->customer_name}",
                'detail' => fn (Booking $b) => trim($b->snap('package', 'en').' · '.Money::format((float) $b->total, 'en'), ' ·'),
                'link' => fn (Booking $b) => "bookings/{$b->id}",
                // Its documents and payments went to the Trash with it.
                'after' => fn (Booking $b, $since) => $this->restoreChildren([$b->documents(), $b->payments()], $since),
                // Deleting for good: the passport/NID files go too (documents, payments and updates follow in the database).
                'purge' => function (Booking $b) {
                    $b->documents()->withTrashed()->get()->each(fn ($document) => Storage::disk('local')->delete($document->path));
                },
            ],
            'payments' => [
                'model' => Payment::class,
                'label' => 'Payment',
                'with' => ['booking'],
                'title' => fn (Payment $p) => Money::format((float) $p->amount, 'en').' · '.($p->booking?->reference ?? 'booking in the Trash'),
                'detail' => fn (Payment $p) => $p->method,
                'blocker' => fn (Payment $p) => $p->booking ? $this->overpays($p) : 'Restore its booking first.',
                'link' => fn (Payment $p) => "bookings/{$p->booking_id}",
                'after' => fn (Payment $p) => app(BookingService::class)->refreshPayments($p->booking),
            ],
            'inquiries' => [
                'model' => Inquiry::class,
                'label' => 'Inquiry',
                'title' => fn (Inquiry $i) => $i->name ?: 'Inquiry #'.$i->id,
                'detail' => fn (Inquiry $i) => $i->phone,
                'link' => fn () => 'inquiries',
            ],
            'packages' => [
                'model' => Package::class,
                'label' => 'Package',
                'with' => ['service'],
                'title' => fn (Package $p) => $t($p, 'name'),
                'detail' => fn (Package $p) => $p->service ? $t($p->service, 'name') : 'Service in the Trash',
                'blocker' => fn (Package $p) => $p->service ? null : 'Restore its service first.',
                'link' => fn (Package $p) => "packages/{$p->id}",
            ],
            'services' => [
                'model' => Service::class,
                'label' => 'Service',
                'title' => fn (Service $s) => $t($s, 'name'),
                'link' => fn () => 'services',
            ],
            'pages' => [
                'model' => Page::class,
                'label' => 'Page',
                'title' => fn (Page $p) => $t($p, 'title'),
                'detail' => fn (Page $p) => $p->type === Page::TYPE_CAMPAIGN ? 'Campaign page' : 'Page',
                'link' => fn (Page $p) => "pages/{$p->id}",
            ],
            'posts' => [
                'model' => Post::class,
                'label' => 'Blog post',
                'title' => fn (Post $p) => $t($p, 'title'),
                'link' => fn (Post $p) => "blog/{$p->id}",
            ],
            'post_categories' => [
                'model' => PostCategory::class,
                'label' => 'Blog category',
                'title' => fn (PostCategory $c) => $t($c, 'name'),
                'link' => fn () => 'blog',
            ],
            'reviews' => [
                'model' => Review::class,
                'label' => 'Review',
                'title' => fn (Review $r) => $t($r, 'name'),
                'link' => fn () => 'reviews',
            ],
            'gallery_albums' => [
                'model' => GalleryAlbum::class,
                'label' => 'Gallery album',
                'title' => fn (GalleryAlbum $a) => $t($a, 'title'),
                'link' => fn (GalleryAlbum $a) => "gallery/{$a->id}",
            ],
            'gallery_items' => [
                'model' => GalleryItem::class,
                'label' => 'Gallery photo or video',
                'with' => ['album'],
                'title' => fn (GalleryItem $i) => ($i->type === 'video' ? 'Video' : 'Photo').' in '.($i->album ? $t($i->album, 'title') : 'an album in the Trash'),
                'blocker' => fn (GalleryItem $i) => $i->album ? null : 'Restore its album first.',
                'link' => fn (GalleryItem $i) => "gallery/{$i->album_id}",
            ],
            'media' => [
                'model' => Media::class,
                'label' => 'Image',
                'title' => fn (Media $m) => $m->original_name,
                'detail' => fn (Media $m) => $m->usage,
                // Deleting for good removes the files; an image something still points at stays.
                'purge_blocker' => fn (Media $m) => ($used = app(MediaUsage::class)->of($m)) ? 'Still used: '.implode('; ', $used).'.' : null,
                'purge' => function (Media $m) {
                    Storage::disk('public')->delete(collect($m->variants ?? [])->pluck('path')->filter()->all());
                    if ($m->directory) {
                        Storage::disk('local')->deleteDirectory($m->directory);
                    }
                },
                'link' => fn () => 'media',
            ],
            'users' => [
                'model' => User::class,
                'label' => 'User',
                'title' => fn (User $u) => $u->name,
                'detail' => fn (User $u) => $u->trashed_values['email'] ?? $u->email,
                'link' => fn () => 'users',
                'after' => fn () => app(PermissionRegistrar::class)->forgetCachedPermissions(),
            ],
            'roles' => [
                'model' => Role::class,
                'label' => 'Role',
                'title' => fn (Role $r) => $r->trashed_values['name'] ?? $r->name,
                'link' => fn () => 'roles',
                'after' => fn () => app(PermissionRegistrar::class)->forgetCachedPermissions(),
            ],
        ];
    }

    /**
     * @return array{model: class-string<Model>, label: string, title: Closure}
     */
    public function type(string $key): array
    {
        return $this->types()[$key] ?? abort(404);
    }

    /**
     * Bring a record back, with whatever left with it.
     */
    public function restore(string $key, int $id): Model
    {
        $type = $this->type($key);
        $model = $type['model']::onlyTrashed()->with($type['with'] ?? [])->findOrFail($id);

        if ($blocker = $this->blocker($key, $model)) {
            abort(422, $blocker);
        }

        DB::transaction(function () use ($type, $model) {
            $since = $model->deleted_at;
            $model->restore();
            if (isset($type['after'])) {
                ($type['after'])($model, $since);
            }
        });

        Cache::forget('sitemap.xml');

        return $model;
    }

    /**
     * Delete a record in the Trash for good, with its private files. Super
     * admins only (trash.delete); it cannot be undone.
     */
    public function purge(string $key, int $id): void
    {
        $type = $this->type($key);
        $model = $type['model']::onlyTrashed()->findOrFail($id);

        if ($blocker = $this->purgeBlocker($key, $model)) {
            abort(422, $blocker);
        }

        DB::transaction(function () use ($type, $model) {
            if (isset($type['purge'])) {
                ($type['purge'])($model);
            }
            $model->forceDelete();
            // Its history goes with it (for a booking: customer details in the activity log).
            Activity::where('subject_type', $model->getMorphClass())->where('subject_id', $model->getKey())->delete();
        });

        Cache::forget('sitemap.xml');
    }

    public function purgeBlocker(string $key, Model $model): ?string
    {
        $type = $this->type($key);

        if ($model instanceof Service && Package::withTrashed()->where('service_id', $model->getKey())->exists()) {
            return 'Its packages (including any in the Trash) must be deleted for good first.';
        }

        return isset($type['purge_blocker']) ? ($type['purge_blocker'])($model) : null;
    }

    public function blocker(string $key, Model $model): ?string
    {
        $type = $this->type($key);

        return isset($type['blocker']) ? ($type['blocker'])($model) : null;
    }

    /**
     * Restore the children that were trashed together with their parent.
     *
     * @param  list<HasMany>  $relations
     */
    /**
     * A paid payment cannot come back if the booking has been paid since:
     * restoring it would take the paid amount above the total.
     */
    private function overpays(Payment $payment): ?string
    {
        $booking = $payment->booking;

        if ($payment->status !== 'paid') {
            return null;
        }

        $after = (float) $booking->paid_amount + (float) $payment->amount;

        if ($after <= (float) $booking->total + 0.005) {
            return null;
        }

        return sprintf(
            'Restoring this payment would take %s to %s paid, above its %s total (%s too much). It has been paid since this payment was deleted; record a refund or correct the payments instead.',
            $booking->reference,
            Money::format($after, 'en'),
            Money::format((float) $booking->total, 'en'),
            Money::format($after - (float) $booking->total, 'en'),
        );
    }

    private function restoreChildren(array $relations, $since): void
    {
        foreach ($relations as $relation) {
            $relation->onlyTrashed()->where('deleted_at', '>=', $since->copy()->subMinute())->get()->each->restore();
        }
    }
}
