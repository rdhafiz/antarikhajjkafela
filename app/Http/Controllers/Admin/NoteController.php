<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Booking\BookingLog;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Inquiry;
use App\Models\Note;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Staff notes on bookings and inquiries. Each note is added on its own with
 * its author and time; only its author or a super admin can remove it.
 */
class NoteController extends Controller
{
    private const TYPES = ['bookings' => Booking::class, 'inquiries' => Inquiry::class];

    public function __construct(private readonly BookingLog $log) {}

    public function store(Request $request, string $type, int $id): JsonResponse
    {
        $notable = $this->notable($type, $id);
        $data = $request->validate(['body' => ['required', 'string', 'max:5000']], ['body.required' => 'Write the note first.']);

        $note = $notable->notes()->create(['body' => trim($data['body']), 'user_id' => $request->user()->id]);

        if ($notable instanceof Booking) {
            $this->log->record($notable, 'note_added', ['note' => Str::limit($note->body, 200)], $request->user());
        }

        return response()->json(['data' => $this->list($notable, $request)], 201);
    }

    public function destroy(Request $request, string $type, int $id, Note $note): JsonResponse
    {
        $notable = $this->notable($type, $id);
        abort_unless($note->notable_type === $notable->getMorphClass() && (int) $note->notable_id === (int) $notable->getKey(), 404);
        abort_unless($request->user()->isSuperAdmin() || $request->user()->id === $note->user_id, 403, 'Only the author or a super admin can remove a note.');

        $note->delete();

        if ($notable instanceof Booking) {
            $this->log->record($notable, 'note_deleted', ['note' => Str::limit($note->body, 200)], $request->user());
        }

        return response()->json(['data' => $this->list($notable, $request)]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function list(Model $notable, Request $request): array
    {
        return $notable->notes()->with('author:id,name')->get()->map(fn (Note $n) => $n->present($request->user()))->all();
    }

    private function notable(string $type, int $id): Model
    {
        $class = self::TYPES[$type] ?? abort(404);
        Gate::authorize("{$type}.update");

        return $class::findOrFail($id);
    }
}
