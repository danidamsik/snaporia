<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Photo;
use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicEventController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'date' => ['nullable', 'date'],
            'location' => ['nullable', 'string', 'max:100'],
        ]);

        $events = Event::query()
            ->where('is_published', true)
            ->withCount('photos')
            ->with('coverPhoto')
            ->when($filters['q'] ?? null, function ($query, string $keyword) {
                $query->where(function ($query) use ($keyword) {
                    $query
                        ->where('name', 'like', "%{$keyword}%")
                        ->orWhere('location', 'like', "%{$keyword}%")
                        ->orWhereDate('date', $keyword)
                        ->orWhereHas('photos', fn ($photoQuery) => $photoQuery->where('filename', 'like', "%{$keyword}%"));
                });
            })
            ->when($filters['date'] ?? null, fn ($query, string $date) => $query->whereDate('date', $date))
            ->when($filters['location'] ?? null, fn ($query, string $location) => $query->where('location', 'like', "%{$location}%"))
            ->latest('date')
            ->paginate(9)
            ->withQueryString()
            ->through(fn (Event $event) => $this->eventPayload($event));

        return Inertia::render('Public/Events/Index', [
            'events' => $events,
            'filters' => [
                'q' => $filters['q'] ?? '',
                'date' => $filters['date'] ?? '',
                'location' => $filters['location'] ?? '',
            ],
        ]);
    }

    public function show(Request $request, Event $event): Response
    {
        abort_unless($event->is_published, 404);

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $perPage = (int) Setting::query()
            ->where('key', 'public_gallery_per_page')
            ->value('value') ?: 24;

        $purchasedPhotoIds = $this->purchasedPhotoIds($request);
        $isPackagePurchased = $this->isPackagePurchased($request, $event);

        $photos = Photo::query()
            ->where('event_id', $event->id)
            ->whereNotNull('watermarked_path')
            ->when($filters['q'] ?? null, fn ($query, string $keyword) => $query->where('filename', 'like', "%{$keyword}%"))
            ->orderBy('sort_order')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Photo $photo) => $this->photoPayload($photo, $purchasedPhotoIds));

        return Inertia::render('Public/Events/Show', [
            'event' => $this->eventPayload($event->loadCount('photos'), $isPackagePurchased),
            'photos' => $photos,
            'filters' => [
                'q' => $filters['q'] ?? '',
            ],
        ]);
    }

    private function eventPayload(Event $event, bool $isPackagePurchased = false): array
    {
        $coverPhoto = $event->coverPhoto;

        return [
            'id' => $event->id,
            'name' => $event->name,
            'description' => $event->description,
            'date' => $event->date?->toDateString(),
            'location' => $event->location,
            'price_per_photo' => (float) $event->price_per_photo,
            'price_package' => (float) $event->price_package,
            'photos_count' => $event->photos_count ?? $event->photos()->count(),
            'cover_url' => $coverPhoto ? route('public.photos.watermarked', $coverPhoto) : null,
            'url' => route('events.show', $event),
            'package_checkout_url' => route('checkout.package.show', $event),
            'is_package_purchased' => $isPackagePurchased,
        ];
    }

    private function photoPayload(Photo $photo, array $purchasedPhotoIds = []): array
    {
        return [
            'id' => $photo->id,
            'filename' => $photo->filename,
            'sort_order' => $photo->sort_order,
            'watermarked_url' => route('public.photos.watermarked', $photo),
            'preview_url' => route('public.photos.preview', $photo),
            'download_url' => route('public.photos.download', $photo),
            'checkout_url' => route('checkout.single.show', ['photos' => [$photo->id]]),
            'is_purchased' => in_array($photo->id, $purchasedPhotoIds, true),
        ];
    }

    private function purchasedPhotoIds(Request $request): array
    {
        if (! $request->user()) {
            return [];
        }

        return OrderItem::query()
            ->whereHas('order', fn ($query) => $query
                ->where('user_id', $request->user()->id)
                ->where('status', Order::STATUS_PAID)
            )
            ->pluck('photo_id')
            ->unique()
            ->values()
            ->all();
    }

    private function isPackagePurchased(Request $request, Event $event): bool
    {
        if (! $request->user()) {
            return false;
        }

        $hasPaidPackage = Order::query()
            ->where('user_id', $request->user()->id)
            ->where('event_id', $event->id)
            ->where('type', Order::TYPE_PACKAGE)
            ->where('status', Order::STATUS_PAID)
            ->exists();

        if ($hasPaidPackage) {
            return true;
        }

        $eventPhotoIds = $event->photos()->pluck('id');
        if ($eventPhotoIds->isEmpty()) {
            return false;
        }

        $paidPhotoIds = OrderItem::query()
            ->whereIn('photo_id', $eventPhotoIds)
            ->whereHas('order', fn ($query) => $query
                ->where('user_id', $request->user()->id)
                ->where('event_id', $event->id)
                ->where('status', Order::STATUS_PAID))
            ->pluck('photo_id')
            ->unique()
            ->values();

        return $eventPhotoIds->diff($paidPhotoIds)->isEmpty();
    }
}
