<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EventMonitoringController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Event::class);

        $filters = $request->validate([
            'admin_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('role', User::ROLE_ADMIN)],
            'status' => ['nullable', Rule::in(['published', 'draft'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $events = Event::query()
            ->with('admin:id,name,email')
            ->withCount(['photos', 'orders'])
            ->when($filters['admin_id'] ?? null, fn ($query, int|string $adminId) => $query->where('admin_id', $adminId))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('is_published', $status === 'published'))
            ->when($filters['date_from'] ?? null, fn ($query, string $date) => $query->whereDate('date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, string $date) => $query->whereDate('date', '<=', $date))
            ->when($filters['q'] ?? null, function ($query, string $keyword) {
                $query->where(function ($query) use ($keyword) {
                    $query
                        ->where('name', 'like', "%{$keyword}%")
                        ->orWhere('location', 'like', "%{$keyword}%")
                        ->orWhereDate('date', $keyword)
                        ->orWhereHas('photos', fn ($photoQuery) => $photoQuery->where('filename', 'like', "%{$keyword}%"));
                });
            })
            ->latest('date')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Event $event) => [
                'id' => $event->id,
                'name' => $event->name,
                'date' => $event->date?->toDateString(),
                'location' => $event->location,
                'price_per_photo' => (float) $event->price_per_photo,
                'price_package' => (float) $event->price_package,
                'is_published' => $event->is_published,
                'photos_count' => $event->photos_count,
                'orders_count' => $event->orders_count,
                'public_url' => $event->is_published ? route('events.show', $event) : null,
                'admin' => [
                    'id' => $event->admin->id,
                    'name' => $event->admin->name,
                    'email' => $event->admin->email,
                ],
            ]);

        return Inertia::render('SuperAdmin/Monitoring/Events', [
            'events' => $events,
            'admins' => User::query()
                ->where('role', User::ROLE_ADMIN)
                ->orderBy('name')
                ->get(['id', 'name']),
            'filters' => [
                'admin_id' => $filters['admin_id'] ?? '',
                'status' => $filters['status'] ?? '',
                'date_from' => $filters['date_from'] ?? '',
                'date_to' => $filters['date_to'] ?? '',
                'q' => $filters['q'] ?? '',
            ],
        ]);
    }
}
