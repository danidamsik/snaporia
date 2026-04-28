<?php

namespace App\Http\Controllers;

use App\Models\Event;
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

        $photos = Photo::query()
            ->where('event_id', $event->id)
            ->whereNotNull('watermarked_path')
            ->when($filters['q'] ?? null, fn ($query, string $keyword) => $query->where('filename', 'like', "%{$keyword}%"))
            ->orderBy('sort_order')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Photo $photo) => $this->photoPayload($photo));

        return Inertia::render('Public/Events/Show', [
            'event' => $this->eventPayload($event->loadCount('photos')),
            'photos' => $photos,
            'filters' => [
                'q' => $filters['q'] ?? '',
            ],
        ]);
    }

    private function eventPayload(Event $event): array
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
        ];
    }

    private function photoPayload(Photo $photo): array
    {
        return [
            'id' => $photo->id,
            'filename' => $photo->filename,
            'sort_order' => $photo->sort_order,
            'watermarked_url' => route('public.photos.watermarked', $photo),
            'checkout_url' => route('checkout.single.show', ['photos' => [$photo->id]]),
        ];
    }
}
