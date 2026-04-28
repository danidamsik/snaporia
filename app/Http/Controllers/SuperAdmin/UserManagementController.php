<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $filters = $request->validate([
            'role' => ['nullable', Rule::in([User::ROLE_SUPER_ADMIN, User::ROLE_ADMIN, User::ROLE_VISITOR])],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $users = User::query()
            ->withCount(['events', 'orders'])
            ->when($filters['role'] ?? null, fn ($query, string $role) => $query->where('role', $role))
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('is_active', $status === 'active'))
            ->when($filters['q'] ?? null, function ($query, string $keyword) {
                $query->where(function ($query) use ($keyword) {
                    $query
                        ->where('name', 'like', "%{$keyword}%")
                        ->orWhere('email', 'like', "%{$keyword}%");
                });
            })
            ->orderByRaw("FIELD(role, 'super_admin', 'admin', 'visitor')")
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (User $user) => $this->userPayload($user, $request->user()));

        return Inertia::render('SuperAdmin/Users/Index', [
            'users' => $users,
            'filters' => [
                'role' => $filters['role'] ?? '',
                'status' => $filters['status'] ?? '',
                'q' => $filters['q'] ?? '',
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('createAdmin', User::class);

        return Inertia::render('SuperAdmin/Users/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('createAdmin', User::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        return redirect()
            ->route('super-admin.users.index')
            ->with('success', 'Akun Admin berhasil dibuat.');
    }

    public function edit(User $user): Response|RedirectResponse
    {
        if (! $user->isAdmin()) {
            return redirect()
                ->route('super-admin.users.index')
                ->with('error', 'Hanya akun Admin yang dapat diubah dari halaman ini.');
        }

        if ($this->hasOperationalData($user)) {
            return redirect()
                ->route('super-admin.users.index')
                ->with('error', 'Akun Admin tidak dapat diubah karena sudah memiliki data operasional.');
        }

        $this->authorize('update', $user);

        return Inertia::render('SuperAdmin/Users/Edit', [
            'managedUser' => $this->userPayload($user->loadCount(['events', 'orders']), request()->user()),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        if (! $user->isAdmin()) {
            return redirect()
                ->route('super-admin.users.index')
                ->with('error', 'Hanya akun Admin yang dapat diubah dari halaman ini.');
        }

        if ($this->hasOperationalData($user)) {
            return redirect()
                ->route('super-admin.users.index')
                ->with('error', 'Akun Admin tidak dapat diubah karena sudah memiliki data operasional.');
        }

        $this->authorize('update', $user);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'confirmed', Rules\Password::defaults()],
            'is_active' => ['required', 'boolean'],
        ]);

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'is_active' => $validated['is_active'],
        ]);

        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        return redirect()
            ->route('super-admin.users.index')
            ->with('success', 'Akun Admin berhasil diperbarui.');
    }

    public function deactivate(Request $request, User $user): RedirectResponse
    {
        $this->authorize('deactivate', $user);

        $user->forceFill([
            'is_active' => false,
        ])->save();

        return back()->with('success', 'Akun user berhasil dinonaktifkan.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->id === $user->id) {
            return back()->with('error', 'Akun yang sedang digunakan tidak dapat dihapus.');
        }

        if ($this->hasOperationalData($user)) {
            return back()->with('error', 'Akun tidak dapat dihapus karena masih memiliki event atau order terkait.');
        }

        $this->authorize('delete', $user);

        $user->delete();

        return back()->with('success', 'Akun user berhasil dihapus.');
    }

    private function userPayload(User $user, User $actor): array
    {
        $eventsCount = $user->events_count ?? $user->events()->count();
        $ordersCount = $user->orders_count ?? $user->orders()->count();
        $hasOperationalData = $eventsCount > 0 || $ordersCount > 0;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'is_active' => $user->is_active,
            'events_count' => $eventsCount,
            'orders_count' => $ordersCount,
            'has_operational_data' => $hasOperationalData,
            'can_edit' => $actor->can('update', $user),
            'can_deactivate' => $actor->can('deactivate', $user) && $user->is_active,
            'can_delete' => $actor->can('delete', $user),
        ];
    }

    private function hasOperationalData(User $user): bool
    {
        return $user->events()->exists() || $user->orders()->exists();
    }
}
