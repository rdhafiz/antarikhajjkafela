<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Visits\VisitReport;
use App\Http\Controllers\Controller;
use App\Models\PageVisit;
use App\Support\Csv;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use PDOException;
use Symfony\Component\HttpFoundation\Response;

class VisitController extends Controller
{
    private const COLUMNS = [
        'id', 'visited_at', 'subject_type', 'subject_id', 'title', 'deleted', 'path', 'url', 'referrer',
        'visitor_id', 'session_id', 'ip', 'location', 'device_type', 'platform', 'browser', 'is_bot', 'locale',
        'channel', 'channel_label', 'source', 'in_app', 'utm_source', 'utm_medium', 'utm_campaign', 'bookings', 'enquiries',
    ];

    public function __construct(private VisitReport $report) {}

    public function sources(Request $request): Response
    {
        $filters = $this->query($request);

        return $this->guard(fn () => response()->json($this->report->sources($filters)));
    }

    public function pages(Request $request): Response
    {
        $filters = $this->query($request);

        return $this->guard(fn () => response()->json($this->report->pages($filters)));
    }

    public function index(Request $request): Response
    {
        $filters = $this->query($request);

        return $this->guard(fn () => response()->json($this->report->paginate($filters)));
    }

    public function filters(): Response
    {
        return $this->guard(fn () => response()->json(['data' => $this->report->filterOptions()]));
    }

    /** CSV of the current filter (UTF-8 BOM so Excel reads Bangla). */
    public function export(Request $request): Response
    {
        $filters = $this->query($request);

        return $this->guard(function () use ($filters) {
            // Fail before the download starts, not halfway through the file.
            $this->report->filtered($filters)->exists();

            return response()->streamDownload(function () use ($filters) {
                $out = fopen('php://output', 'w');
                fwrite($out, "\xEF\xBB\xBF");
                fputcsv($out, self::COLUMNS);

                // newest first, like the screen
                foreach ($this->report->filtered($filters)->lazyByIdDesc(500)->chunk(500) as $chunk) {
                    foreach ($this->report->shape(collect($chunk->all())) as $r) {
                        fputcsv($out, Csv::row([
                            $r['id'], $r['visited_at'], $r['subject_type'], $r['subject_id'], $r['title'], $r['deleted'] ? 1 : 0,
                            $r['path'], $r['url'], $r['referrer'], $r['visitor_id'], $r['session_id'], $r['ip'], $r['location']['label'] ?? null,
                            $r['device_type'], $r['platform'], $r['browser'], $r['is_bot'] ? 1 : 0, $r['locale'],
                            $r['channel'], $r['channel_label'], $r['source'], $r['in_app'],
                            $r['utm_source'], $r['utm_medium'], $r['utm_campaign'],
                            $r['converted']['bookings'], $r['converted']['enquiries'],
                        ]));
                    }
                }

                fclose($out);
            }, 'page-visits-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        });
    }

    /** @return array<string, mixed> */
    private function query(Request $request): array
    {
        return $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'subject_type' => ['nullable', Rule::in(PageVisit::SUBJECTS)],
            'include_bots' => ['nullable', 'boolean'],
            'sort_by' => ['nullable', 'string', 'max:32'],
            'sort_order' => ['nullable', Rule::in(['asc', 'desc'])],
            ...array_fill_keys(
                array_diff(VisitReport::EXACT_FILTERS, ['subject_type']),
                ['nullable', 'string', 'max:2048'],
            ),
        ]);
    }

    /** A visit log that cannot be queried is an outage, not an empty report. */
    private function guard(Closure $action): Response
    {
        try {
            return $action();
        } catch (QueryException|PDOException $e) {
            Log::warning('Page visit report: the visit log could not be queried.', ['error' => $e->getMessage()]);

            return response()->json(['message' => 'The visit log is unavailable right now.'], 503);
        }
    }
}
