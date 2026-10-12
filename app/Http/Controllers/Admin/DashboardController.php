<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\GalleryAlbum;
use App\Models\Inquiry;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Overview numbers for the dashboard. Each block is only included when the
 * user may see that module.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = [];

        if ($user->can('bookings.view')) {
            $monthStart = now()->startOfMonth();
            $since = now()->subMonths(5)->startOfMonth();

            $months = collect(range(5, 0))->mapWithKeys(fn (int $ago) => [now()->subMonths($ago)->format('Y-m') => 0]);
            $perMonth = Booking::query()->where('created_at', '>=', $since)->pluck('created_at')
                ->countBy(fn ($date) => $date->format('Y-m'));

            $data['bookings'] = [
                'pending' => Booking::where('status', 'pending')->count(),
                'this_month' => Booking::where('created_at', '>=', $monthStart)->count(),
                'travelers_this_month' => (int) Booking::where('created_at', '>=', $monthStart)->where('status', '!=', 'cancelled')->sum('travelers'),
                'collected_this_month' => (float) Payment::where('status', 'paid')->where('paid_at', '>=', $monthStart)->sum('amount'),
                'trend' => $months->map(fn (int $zero, string $month) => ['month' => $month, 'count' => $perMonth[$month] ?? 0])->values(),
                'recent' => Booking::latest('id')->limit(5)->get()->map(fn (Booking $booking) => [
                    'id' => $booking->id,
                    'reference' => $booking->reference,
                    'status' => $booking->status,
                    'customer_name' => $booking->customer_name,
                    'package' => $booking->snap('package', 'en'),
                    'total' => (float) $booking->total,
                    'is_unread' => $booking->read_at === null,
                    'created_at' => $booking->created_at?->toIso8601String(),
                ]),
            ];
        }

        if ($user->can('inquiries.view')) {
            $data['inquiries'] = [
                'new' => Inquiry::where('status', 'new')->count(),
                'recent' => Inquiry::latest('id')->limit(5)->get()->map(fn (Inquiry $inquiry) => [
                    'id' => $inquiry->id,
                    'name' => $inquiry->name,
                    'phone' => $inquiry->phone,
                    'source' => $inquiry->source,
                    'status' => $inquiry->status,
                    'is_unread' => $inquiry->read_at === null,
                    'created_at' => $inquiry->created_at?->toIso8601String(),
                ]),
            ];
        }

        if ($user->can('packages.view')) {
            $data['packages'] = ['published' => Package::where('status', Package::STATUS_PUBLISHED)->count()];
        }

        if ($user->can('blog.view')) {
            $data['posts'] = ['published' => Post::query()->live()->count()];
        }

        if ($user->can('gallery.view')) {
            $data['gallery'] = ['albums' => GalleryAlbum::count()];
        }

        return response()->json($data);
    }
}
