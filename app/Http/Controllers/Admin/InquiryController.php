<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Inquiry;
use App\Support\Csv;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InquiryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $filters = $this->filters($request);

        $inquiries = $this->query($filters)
            ->with('page:id,title,type')
            ->when(($filters['sort'] ?? null) === 'oldest', fn ($q) => $q->oldest('id'), fn ($q) => $q->latest('id'))
            ->paginate($filters['per_page'] ?? 20);

        return response()->json([
            'data' => collect($inquiries->items())->map(fn (Inquiry $inquiry) => $this->format($inquiry)),
            'meta' => [
                'current_page' => $inquiries->currentPage(),
                'last_page' => $inquiries->lastPage(),
                'per_page' => $inquiries->perPage(),
                'total' => $inquiries->total(),
                'from' => $inquiries->firstItem(),
                'to' => $inquiries->lastItem(),
            ],
            'counts' => [
                'unread' => Inquiry::whereNull('read_at')->count(),
                'new' => Inquiry::where('status', 'new')->count(),
                'statuses' => Inquiry::query()->toBase()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')->map(fn ($n) => (int) $n),
                'sources' => Inquiry::query()->toBase()->selectRaw('source, count(*) as n')->groupBy('source')->pluck('n', 'source')->map(fn ($n) => (int) $n),
            ],
        ]);
    }

    public function show(Inquiry $inquiry): JsonResponse
    {
        if (! $inquiry->read_at) {
            $inquiry->forceFill(['read_at' => now()])->save();
        }

        return response()->json(['data' => $this->format($inquiry->load('page:id,title,type,slug'))]);
    }

    public function update(Request $request, Inquiry $inquiry): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(Inquiry::STATUSES)],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $inquiry->update($data);

        return response()->json(['data' => $this->format($inquiry)]);
    }

    public function destroy(Inquiry $inquiry): JsonResponse
    {
        $inquiry->delete();

        return response()->json(status: 204);
    }

    /**
     * CSV export of the current filter (opens in Excel; UTF-8 BOM for Bangla).
     */
    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);

        return response()->streamDownload(function () use ($filters) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Date', 'Source', 'Page', 'Name', 'Phone', 'Email', 'Details', 'Status', 'Notes']);

            $this->query($filters)->with('page:id,title')->latest('id')->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $inquiry) {
                    fputcsv($out, Csv::row([
                        $inquiry->created_at?->format('Y-m-d H:i'),
                        $inquiry->source,
                        $inquiry->page?->getTranslation('title', 'en'),
                        $inquiry->name,
                        Csv::phone($inquiry->phone),
                        $inquiry->email,
                        collect($inquiry->readableData())->map(fn ($v, $k) => "{$k}: ".str_replace("\n", '; ', $v))->implode(' | '),
                        $inquiry->status,
                        $inquiry->notes,
                    ]));
                }
            });

            fclose($out);
        }, 'inquiries-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    /**
     * Filters shared by the list and the CSV export.
     *
     * @return array<string, mixed>
     */
    private function filters(Request $request): array
    {
        return $request->validate([
            'status' => ['nullable', Rule::in(Inquiry::STATUSES)],
            'source' => ['nullable', 'string', 'max:32'],
            'read' => ['nullable', Rule::in(['unread', 'read'])],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'sort' => ['nullable', Rule::in(['newest', 'oldest'])],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
    }

    private function query(array $filters)
    {
        return Inquiry::query()
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['source'] ?? null, fn ($q, $source) => $q->where('source', $source))
            ->when($filters['read'] ?? null, fn ($q, $read) => $read === 'unread' ? $q->whereNull('read_at') : $q->whereNotNull('read_at'))
            ->when($filters['from'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($filters['to'] ?? null, fn ($q, $date) => $q->whereDate('created_at', '<=', $date))
            ->when($filters['search'] ?? null, function ($q, $search) {
                // "!" escapes % and _ so they are matched literally (the same in MySQL and SQLite).
                $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
                $q->where(fn ($q) => $q->whereRaw("name like ? escape '!'", [$like])
                    ->orWhereRaw("phone like ? escape '!'", [$like])
                    ->orWhereRaw("email like ? escape '!'", [$like]));
            });
    }

    /**
     * @return array<string, mixed>
     */
    private function format(Inquiry $inquiry): array
    {
        return [
            'id' => $inquiry->id,
            'source' => $inquiry->source,
            'name' => $inquiry->name,
            'phone' => $inquiry->phone,
            'email' => $inquiry->email,
            'data' => (object) $inquiry->readableData(),
            'page' => $inquiry->page ? ['id' => $inquiry->page->id, 'title' => $inquiry->page->getTranslation('title', 'en'), 'type' => $inquiry->page->type] : null,
            'status' => $inquiry->status,
            'notes' => $inquiry->notes,
            'locale' => $inquiry->locale,
            'read' => $inquiry->read_at !== null,
            'created_at' => $inquiry->created_at?->toIso8601String(),
        ];
    }
}
