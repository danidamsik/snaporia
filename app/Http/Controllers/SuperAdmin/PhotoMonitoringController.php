<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PhotoMonitoringController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Photo::class);

        $filters = $request->validate([
            'admin_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('role', User::ROLE_ADMIN)],
            'event_id' => ['nullable', 'integer', 'exists:events,id'],
            'status' => ['nullable', Rule::in(['ready', 'watermark_failed'])],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $photos = Photo::query()
            ->with('event.admin:id,name,email')
            ->withCount('orderItems')
            ->when($filters['admin_id'] ?? null, fn ($query, int|string $adminId) => $query->whereHas('event', fn ($eventQuery) => $eventQuery->where('admin_id', $adminId)))
            ->when($filters['event_id'] ?? null, fn ($query, int|string $eventId) => $query->where('event_id', $eventId))
            ->when($filters['q'] ?? null, fn ($query, string $keyword) => $query->where('filename', 'like', "%{$keyword}%"))
            ->orderByDesc('id')
            ->paginate(24)
            ->withQueryString()
            ->through(fn (Photo $photo) => $this->photoPayload($photo));

        if (($filters['status'] ?? '') !== '') {
            $photos->setCollection($photos->getCollection()->filter(
                fn (array $photo) => $photo['status'] === $filters['status']
            )->values());
        }

        return Inertia::render('SuperAdmin/Monitoring/Photos', [
            'photos' => $photos,
            'admins' => User::query()
                ->where('role', User::ROLE_ADMIN)
                ->orderBy('name')
                ->get(['id', 'name']),
            'events' => Event::query()
                ->orderBy('name')
                ->get(['id', 'name', 'admin_id']),
            'filters' => [
                'admin_id' => $filters['admin_id'] ?? '',
                'event_id' => $filters['event_id'] ?? '',
                'status' => $filters['status'] ?? '',
                'q' => $filters['q'] ?? '',
            ],
        ]);
    }

    public function preview(Photo $photo): StreamedResponse
    {
        $this->authorize('view', $photo);

        abort_unless(Storage::disk('local')->exists($photo->watermarked_path), 404);

        return Storage::disk('local')->response($photo->watermarked_path, $photo->filename, [
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    private function photoPayload(Photo $photo): array
    {
        $isReady = Storage::disk('local')->exists($photo->watermarked_path);

        return [
            'id' => $photo->id,
            'filename' => $photo->filename,
            'file_size' => $photo->file_size,
            'mime_type' => $photo->mime_type,
            'sort_order' => $photo->sort_order,
            'status' => $isReady ? 'ready' : 'watermark_failed',
            'preview_url' => $isReady ? route('super-admin.photos.preview', $photo) : null,
            'order_items_count' => $photo->order_items_count,
            'event' => [
                'id' => $photo->event->id,
                'name' => $photo->event->name,
                'is_published' => $photo->event->is_published,
                'price_per_photo' => (float) $photo->event->price_per_photo,
            ],
            'admin' => [
                'id' => $photo->event->admin->id,
                'name' => $photo->event->admin->name,
                'email' => $photo->event->admin->email,
            ],
        ];
    }
}
