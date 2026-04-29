<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Order;
use App\Models\Photo;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function superAdmin(): Response
    {
        return Inertia::render('Dashboard', [
            'dashboardRole' => User::ROLE_SUPER_ADMIN,
            'stats' => [
                $this->stat('Total User', User::query()->count(), 'users'),
                $this->stat('Admin', User::query()->where('role', User::ROLE_ADMIN)->count(), 'camera'),
                $this->stat('Event', Event::query()->count(), 'calendar'),
                $this->stat('Foto', Photo::query()->count(), 'image'),
                $this->stat('Order', Order::query()->count(), 'file'),
                $this->stat('Transaksi', Transaction::query()->count(), 'credit-card'),
                $this->stat('Pendapatan Paid', (float) Order::query()->where('status', Order::STATUS_PAID)->sum('total_amount'), 'banknote', 'currency'),
            ],
            'recentTransactions' => $this->transactionQuery()
                ->latest('id')
                ->take(5)
                ->get()
                ->map(fn (Transaction $transaction) => $this->transactionPayload($transaction, route('super-admin.transactions.show', $transaction))),
            'recentOrders' => [],
            'recentEvents' => Event::query()
                ->with('admin:id,name')
                ->withCount('photos')
                ->latest('id')
                ->take(5)
                ->get()
                ->map(fn (Event $event) => $this->eventPayload($event, true)),
            'quickLinks' => [
                ['label' => 'Manajemen User', 'href' => route('super-admin.users.index'), 'icon' => 'users'],
                ['label' => 'Transaksi', 'href' => route('super-admin.transactions.index'), 'icon' => 'credit-card'],
                ['label' => 'Settings', 'href' => route('super-admin.settings.edit'), 'icon' => 'settings'],
            ],
        ]);
    }

    public function admin(Request $request): Response
    {
        $adminId = $request->user()->id;

        return Inertia::render('Dashboard', [
            'dashboardRole' => User::ROLE_ADMIN,
            'stats' => [
                $this->stat('Event Saya', Event::query()->where('admin_id', $adminId)->count(), 'calendar'),
                $this->stat('Foto Saya', Photo::query()->whereHas('event', fn ($query) => $query->where('admin_id', $adminId))->count(), 'image'),
                $this->stat('Transaksi', Transaction::query()->whereHas('order.event', fn ($query) => $query->where('admin_id', $adminId))->count(), 'credit-card'),
                $this->stat('Total Penjualan', (float) Order::query()
                    ->where('status', Order::STATUS_PAID)
                    ->whereHas('event', fn ($query) => $query->where('admin_id', $adminId))
                    ->sum('total_amount'), 'banknote', 'currency'),
            ],
            'recentTransactions' => $this->transactionQuery()
                ->whereHas('order.event', fn ($query) => $query->where('admin_id', $adminId))
                ->latest('id')
                ->take(5)
                ->get()
                ->map(fn (Transaction $transaction) => $this->transactionPayload($transaction, route('admin.transactions.show', $transaction))),
            'recentOrders' => [],
            'recentEvents' => Event::query()
                ->where('admin_id', $adminId)
                ->withCount('photos')
                ->latest('id')
                ->take(5)
                ->get()
                ->map(fn (Event $event) => $this->eventPayload($event)),
            'quickLinks' => [
                ['label' => 'Buat Event', 'href' => route('admin.events.create'), 'icon' => 'calendar'],
                ['label' => 'Upload Foto', 'href' => route('admin.photos.upload'), 'icon' => 'upload'],
                ['label' => 'Laporan Penjualan', 'href' => route('admin.reports.sales'), 'icon' => 'banknote'],
            ],
        ]);
    }

    private function transactionQuery()
    {
        return Transaction::query()
            ->with(['order.user:id,name,email', 'order.event.admin:id,name', 'order.event:id,admin_id,name,date,location']);
    }

    private function stat(string $label, int|float $value, string $icon, string $format = 'number'): array
    {
        return compact('label', 'value', 'icon', 'format');
    }

    private function transactionPayload(Transaction $transaction, string $url): array
    {
        return [
            'id' => $transaction->id,
            'midtrans_order_id' => $transaction->midtrans_order_id,
            'status' => $transaction->status,
            'order_status' => $transaction->order->status,
            'gross_amount' => (float) $transaction->gross_amount,
            'created_at' => $transaction->created_at?->toIso8601String(),
            'url' => $url,
            'order' => [
                'order_code' => $transaction->order->order_code,
                'type' => $transaction->order->type,
            ],
            'event' => [
                'name' => $transaction->order->event->name,
            ],
            'user' => [
                'name' => $transaction->order->user->name,
            ],
            'admin' => $transaction->order->event->admin ? [
                'name' => $transaction->order->event->admin->name,
            ] : null,
        ];
    }

    private function eventPayload(Event $event, bool $includeAdmin = false): array
    {
        return [
            'id' => $event->id,
            'name' => $event->name,
            'date' => $event->date?->toDateString(),
            'location' => $event->location,
            'is_published' => $event->is_published,
            'photos_count' => $event->photos_count,
            'admin' => $includeAdmin && $event->admin ? [
                'name' => $event->admin->name,
            ] : null,
        ];
    }
}
