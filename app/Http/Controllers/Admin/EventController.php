<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Event::class);

        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['published', 'draft'])],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $events = Event::query()
            ->where('admin_id', $request->user()->id)
            ->withCount(['photos', 'orders'])
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('is_published', $status === 'published'))
            ->when($filters['q'] ?? null, function ($query, string $keyword) {
                $query->where(function ($query) use ($keyword) {
                    $query
                        ->where('name', 'like', "%{$keyword}%")
                        ->orWhere('location', 'like', "%{$keyword}%")
                        ->orWhereDate('date', $keyword);
                });
            })
            ->latest('date')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Event $event) => $this->eventPayload($event, $request));

        return Inertia::render('Admin/Events/Index', [
            'events' => $events,
            'filters' => [
                'status' => $filters['status'] ?? '',
                'q' => $filters['q'] ?? '',
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Event::class);

        return Inertia::render('Admin/Events/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Event::class);

        $event = Event::create([
            ...$this->validatedData($request),
            'admin_id' => $request->user()->id,
        ]);

        Log::info('Event created', [
            'event_id' => $event->id,
            'admin_id' => $request->user()->id,
        ]);

        return redirect()
            ->route('admin.events.index')
            ->with('success', 'Event berhasil dibuat.');
    }

    public function edit(Event $event): Response
    {
        $this->authorize('update', $event);

        return Inertia::render('Admin/Events/Edit', [
            'event' => $this->eventPayload($event->loadCount(['photos', 'orders']), request()),
        ]);
    }

    public function update(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('update', $event);

        $event->update($this->validatedData($request));

        return redirect()
            ->route('admin.events.index')
            ->with('success', 'Event berhasil diperbarui.');
    }

    public function publish(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('update', $event);

        $event->forceFill([
            'is_published' => true,
        ])->save();

        Log::info('Event published', [
            'event_id' => $event->id,
            'admin_id' => $request->user()->id,
        ]);

        return back()->with('success', 'Event berhasil dipublikasikan.');
    }

    public function unpublish(Request $request, Event $event): RedirectResponse
    {
        $this->authorize('update', $event);

        $event->forceFill([
            'is_published' => false,
        ])->save();

        Log::info('Event unpublished', [
            'event_id' => $event->id,
            'admin_id' => $request->user()->id,
        ]);

        return back()->with('success', 'Publikasi event berhasil dibatalkan.');
    }

    public function destroy(Event $event): RedirectResponse
    {
        if ($this->hasOperationalData($event)) {
            return back()->with('error', 'Event tidak dapat dihapus karena sudah memiliki order atau transaksi.');
        }

        $this->authorize('delete', $event);

        $event->photos()->delete();
        $event->delete();

        Log::info('Event deleted', [
            'event_id' => $event->id,
            'admin_id' => request()->user()->id,
        ]);

        return back()->with('success', 'Event berhasil dihapus.');
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'date' => ['nullable', 'date'],
            'location' => ['nullable', 'string', 'max:255'],
            'price_per_photo' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'price_package' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'is_published' => ['required', 'boolean'],
        ]);
    }

    private function eventPayload(Event $event, Request $request): array
    {
        $ordersCount = $event->orders_count ?? $event->orders()->count();

        return [
            'id' => $event->id,
            'name' => $event->name,
            'description' => $event->description,
            'date' => $event->date?->toDateString(),
            'location' => $event->location,
            'price_per_photo' => (float) $event->price_per_photo,
            'price_package' => (float) $event->price_package,
            'is_published' => $event->is_published,
            'photos_count' => $event->photos_count ?? $event->photos()->count(),
            'orders_count' => $ordersCount,
            'has_operational_data' => $ordersCount > 0 || $event->photos()->whereHas('orderItems')->exists(),
            'can_update' => $request->user()->can('update', $event),
            'can_delete' => $request->user()->can('delete', $event),
        ];
    }

    private function hasOperationalData(Event $event): bool
    {
        return $event->orders()->exists() || $event->photos()->whereHas('orderItems')->exists();
    }
}
