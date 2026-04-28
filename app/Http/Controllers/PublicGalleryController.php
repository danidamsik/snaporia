<?php

namespace App\Http\Controllers;

use App\Models\Photo;
use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicGalleryController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $perPage = (int) Setting::query()
            ->where('key', 'public_gallery_per_page')
            ->value('value') ?: 24;

        $photos = Photo::query()
            ->select('id', 'event_id', 'watermarked_path', 'filename', 'sort_order')
            ->with(['event:id,name,date,location,price_per_photo,price_package,is_published'])
            ->whereNotNull('watermarked_path')
            ->whereHas('event', fn ($query) => $query->where('is_published', true))
            ->when($filters['q'] ?? null, function ($query, string $keyword) {
                $query->where(function ($query) use ($keyword) {
                    $query
                        ->where('filename', 'like', "%{$keyword}%")
                        ->orWhereHas('event', fn ($eventQuery) => $eventQuery
                            ->where('name', 'like', "%{$keyword}%")
                            ->orWhere('location', 'like', "%{$keyword}%")
                            ->orWhereDate('date', $keyword)
                        );
                });
            })
            ->orderBy('event_id')
            ->orderBy('sort_order')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Photo $photo) => [
                'id' => $photo->id,
                'filename' => $photo->filename,
                'watermarked_url' => route('public.photos.watermarked', $photo),
                'checkout_url' => route('checkout.single.show', ['photos' => [$photo->id]]),
                'event' => [
                    'id' => $photo->event->id,
                    'name' => $photo->event->name,
                    'date' => $photo->event->date?->toDateString(),
                    'location' => $photo->event->location,
                    'price_per_photo' => (float) $photo->event->price_per_photo,
                    'url' => route('events.show', $photo->event),
                ],
            ]);

        return Inertia::render('Public/Gallery/Index', [
            'photos' => $photos,
            'filters' => [
                'q' => $filters['q'] ?? '',
            ],
        ]);
    }
}
