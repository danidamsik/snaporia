<?php

use App\Http\Controllers\Admin\EventController as AdminEventController;
use App\Http\Controllers\Admin\PhotoController as AdminPhotoController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicEventController;
use App\Http\Controllers\PublicGalleryController;
use App\Http\Controllers\PublicPhotoController;
use App\Http\Controllers\SuperAdmin\UserManagementController;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', [PublicEventController::class, 'index'])->name('public.events.index');

Route::get('/events', [PublicEventController::class, 'index'])->name('events.index');
Route::get('/events/{event}', [PublicEventController::class, 'show'])->name('events.show');

Route::get('/gallery', [PublicGalleryController::class, 'index'])->name('gallery.index');
Route::get('/photos/{photo}/watermarked', [PublicPhotoController::class, 'watermarked'])->name('public.photos.watermarked');

Route::get('/dashboard', function (): RedirectResponse {
    return match (request()->user()->role) {
        User::ROLE_SUPER_ADMIN => redirect()->route('super-admin.dashboard'),
        User::ROLE_ADMIN => redirect()->route('admin.dashboard'),
        default => redirect()->route('visitor.dashboard'),
    };
})->middleware(['auth', 'active', 'verified'])->name('dashboard');

Route::middleware(['auth', 'active', 'verified'])->group(function () {
    Route::get('/super-admin/dashboard', fn () => Inertia::render('Dashboard', [
        'dashboardRole' => User::ROLE_SUPER_ADMIN,
    ]))->middleware('role:super_admin')->name('super-admin.dashboard');

    Route::middleware('role:super_admin')->prefix('super-admin')->name('super-admin.')->group(function () {
        Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserManagementController::class, 'create'])->name('users.create');
        Route::post('/users', [UserManagementController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [UserManagementController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserManagementController::class, 'update'])->name('users.update');
        Route::patch('/users/{user}/deactivate', [UserManagementController::class, 'deactivate'])->name('users.deactivate');
        Route::delete('/users/{user}', [UserManagementController::class, 'destroy'])->name('users.destroy');
    });

    Route::get('/admin/dashboard', fn () => Inertia::render('Dashboard', [
        'dashboardRole' => User::ROLE_ADMIN,
    ]))->middleware('role:admin')->name('admin.dashboard');

    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/events', [AdminEventController::class, 'index'])->name('events.index');
        Route::get('/events/create', [AdminEventController::class, 'create'])->name('events.create');
        Route::post('/events', [AdminEventController::class, 'store'])->name('events.store');
        Route::get('/events/{event}/edit', [AdminEventController::class, 'edit'])->name('events.edit');
        Route::put('/events/{event}', [AdminEventController::class, 'update'])->name('events.update');
        Route::patch('/events/{event}/publish', [AdminEventController::class, 'publish'])->name('events.publish');
        Route::patch('/events/{event}/unpublish', [AdminEventController::class, 'unpublish'])->name('events.unpublish');
        Route::delete('/events/{event}', [AdminEventController::class, 'destroy'])->name('events.destroy');
        Route::get('/events/{event}/photos', [AdminPhotoController::class, 'index'])->name('events.photos.index');

        Route::get('/photos', [AdminPhotoController::class, 'index'])->name('photos.index');
        Route::get('/photos/upload', [AdminPhotoController::class, 'upload'])->name('photos.upload');
        Route::post('/photos/upload', [AdminPhotoController::class, 'store'])->name('photos.store');
        Route::get('/photos/{photo}/preview', [AdminPhotoController::class, 'preview'])->name('photos.preview');
        Route::delete('/photos/{photo}', [AdminPhotoController::class, 'destroy'])->name('photos.destroy');
    });

    Route::get('/visitor/dashboard', fn () => Inertia::render('Dashboard', [
        'dashboardRole' => User::ROLE_VISITOR,
    ]))->middleware('role:visitor')->name('visitor.dashboard');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
