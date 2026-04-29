<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Photo;
use App\Models\Setting;
use App\Services\WatermarkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class PhotoController extends Controller
{
    public function index(Request $request, ?Event $event = null): Response
    {
        $this->authorize('viewAny', Photo::class);

        if ($event) {
            $this->authorize('view', $event);
        }

        $filters = $request->validate([
            'event_id' => ['nullable', 'integer', Rule::exists('events', 'id')->where('admin_id', $request->user()->id)],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $eventId = $event?->id ?? ($filters['event_id'] ?? null);
        $events = $this->ownedEvents($request);

        $photos = Photo::query()
            ->with('event:id,name,admin_id')
            ->whereHas('event', fn ($query) => $query->where('admin_id', $request->user()->id))
            ->withCount('orderItems')
            ->when($eventId, fn ($query, int $id) => $query->where('event_id', $id))
            ->when($filters['q'] ?? null, fn ($query, string $keyword) => $query->where('filename', 'like', "%{$keyword}%"))
            ->orderBy('event_id')
            ->orderBy('sort_order')
            ->paginate(24)
            ->withQueryString()
            ->through(fn (Photo $photo) => $this->photoPayload($photo, $request));

        return Inertia::render('Admin/Photos/Index', [
            'photos' => $photos,
            'events' => $events,
            'selectedEvent' => $eventId ? $events->firstWhere('id', (int) $eventId) : null,
            'filters' => [
                'event_id' => $eventId ? (int) $eventId : '',
                'q' => $filters['q'] ?? '',
            ],
        ]);
    }

    public function upload(Request $request): Response
    {
        $this->authorize('create', Photo::class);

        return Inertia::render('Admin/Photos/Upload', [
            'events' => $this->ownedEvents($request),
            'limits' => [
                'max_file_size_mb' => (int) Setting::query()->where('key', 'upload_max_file_size_mb')->value('value') ?: 15,
                'max_files_per_batch' => (int) Setting::query()->where('key', 'upload_max_files_per_batch')->value('value') ?: 50,
            ],
        ]);
    }

    public function store(Request $request, WatermarkService $watermarkService): RedirectResponse
    {
        $this->authorize('create', Photo::class);

        $maxFileSizeMb = (int) Setting::query()->where('key', 'upload_max_file_size_mb')->value('value') ?: 15;
        $maxFilesPerBatch = (int) Setting::query()->where('key', 'upload_max_files_per_batch')->value('value') ?: 50;

        $validated = $request->validate([
            'event_id' => ['required', 'integer', Rule::exists('events', 'id')->where('admin_id', $request->user()->id)],
            'photos' => ['required', 'array', 'max:'.$maxFilesPerBatch],
            'photos.*' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:'.($maxFileSizeMb * 1024)],
        ]);

        $event = Event::where('admin_id', $request->user()->id)->findOrFail($validated['event_id']);
        $watermarkText = Setting::query()->where('key', 'watermark_text')->value('value') ?: 'Snaporia';
        $watermarkOpacity = (int) Setting::query()->where('key', 'watermark_opacity')->value('value') ?: 25;
        $nextSortOrder = ((int) Photo::where('event_id', $event->id)->max('sort_order')) + 1;
        $result = [
            'success_count' => 0,
            'failed_count' => 0,
            'success_files' => [],
            'failed_files' => [],
        ];

        foreach ($request->file('photos', []) as $file) {
            $originalClientName = $file->getClientOriginalName();
            $extension = strtolower($file->getClientOriginalExtension());
            $displayName = $this->photoFilename($event->name, $nextSortOrder, $extension);
            $safeBaseName = Str::slug(pathinfo($displayName, PATHINFO_FILENAME)) ?: 'photo';
            $uniqueName = $safeBaseName.'-'.Str::ulid().'.'.$extension;
            $watermarkedName = pathinfo($uniqueName, PATHINFO_FILENAME).'.jpg';
            $originalPath = "photos/original/{$event->admin_id}/{$event->id}/{$uniqueName}";
            $watermarkedPath = "photos/watermarked/{$event->admin_id}/{$event->id}/{$watermarkedName}";

            try {
                Storage::disk('local')->putFileAs(dirname($originalPath), $file, basename($originalPath));
                $watermarkService->createPreview($originalPath, $watermarkedPath, $watermarkText, $watermarkOpacity);

                Photo::create([
                    'event_id' => $event->id,
                    'original_path' => $originalPath,
                    'watermarked_path' => $watermarkedPath,
                    'filename' => $displayName,
                    'file_size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                    'sort_order' => $nextSortOrder++,
                ]);

                $result['success_count']++;
                $result['success_files'][] = $displayName;
            } catch (Throwable $exception) {
                Storage::disk('local')->delete([$originalPath, $watermarkedPath]);
                $result['failed_count']++;
                $result['failed_files'][] = [
                    'filename' => $originalClientName,
                    'error' => 'Watermark gagal dibuat.',
                ];

                Log::warning('Photo upload failed', [
                    'event_id' => $event->id,
                    'admin_id' => $request->user()->id,
                    'filename' => $originalClientName,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        Log::info('Bulk photo upload completed', [
            'event_id' => $event->id,
            'admin_id' => $request->user()->id,
            'success_count' => $result['success_count'],
            'failed_count' => $result['failed_count'],
        ]);

        return back()
            ->with($result['success_count'] > 0 ? 'success' : 'warning', $this->uploadMessage($result))
            ->with('upload_result', $result);
    }

    public function destroy(Photo $photo): RedirectResponse
    {
        $this->authorize('delete', $photo);

        if ($photo->orderItems()->exists()) {
            return back()->with('error', 'Foto tidak dapat dihapus karena sudah masuk order item.');
        }

        Storage::disk('local')->delete([$photo->original_path, $photo->watermarked_path]);
        $photo->delete();

        Log::info('Photo deleted', [
            'photo_id' => $photo->id,
            'event_id' => $photo->event_id,
            'admin_id' => request()->user()->id,
        ]);

        return back()->with('success', 'Foto berhasil dihapus.');
    }

    public function preview(Photo $photo): StreamedResponse
    {
        $this->authorize('view', $photo);

        abort_unless(Storage::disk('local')->exists($photo->watermarked_path), 404);

        return Storage::disk('local')->response($photo->watermarked_path, $photo->filename, [
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    private function ownedEvents(Request $request)
    {
        return Event::query()
            ->where('admin_id', $request->user()->id)
            ->orderByDesc('date')
            ->get(['id', 'name', 'date', 'location']);
    }

    private function photoPayload(Photo $photo, Request $request): array
    {
        $orderItemsCount = $photo->order_items_count ?? $photo->orderItems()->count();

        return [
            'id' => $photo->id,
            'event_id' => $photo->event_id,
            'event_name' => $photo->event->name,
            'filename' => $photo->filename,
            'file_size' => $photo->file_size,
            'mime_type' => $photo->mime_type,
            'sort_order' => $photo->sort_order,
            'status' => Storage::disk('local')->exists($photo->watermarked_path) ? 'ready' : 'watermark_failed',
            'watermarked_url' => route('admin.photos.preview', $photo),
            'order_items_count' => $orderItemsCount,
            'can_delete' => $request->user()->can('delete', $photo) && $orderItemsCount === 0,
        ];
    }

    private function uploadMessage(array $result): string
    {
        return "{$result['success_count']} foto berhasil diupload, {$result['failed_count']} foto gagal.";
    }

    private function photoFilename(string $eventName, int $sortOrder, string $extension): string
    {
        $baseName = (string) Str::of($eventName)
            ->lower()
            ->replaceMatches('/[\\\\\/:*?"<>|]+/', '')
            ->replaceMatches('/\s+/', ' ')
            ->trim();
        $baseName = $baseName ?: 'event';

        return sprintf('%s-%02d.%s', $baseName, $sortOrder, $extension);
    }
}
