<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Photo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicPhotoController extends Controller
{
    public function watermarked(Photo $photo): StreamedResponse
    {
        abort_unless($photo->event()->where('is_published', true)->exists(), 404);
        abort_unless(Storage::disk('local')->exists($photo->watermarked_path), 404);

        return Storage::disk('local')->response($photo->watermarked_path, $photo->filename, [
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    public function preview(Request $request, Photo $photo): StreamedResponse
    {
        abort_unless($photo->event()->where('is_published', true)->exists(), 404);

        if ($this->userHasPaidAccess($request, $photo)) {
            abort_unless(Storage::disk('local')->exists($photo->original_path), 404);

            return Storage::disk('local')->response($photo->original_path, $photo->filename, [
                'Cache-Control' => 'private, max-age=300',
            ]);
        }

        abort_unless(Storage::disk('local')->exists($photo->watermarked_path), 404);

        return Storage::disk('local')->response($photo->watermarked_path, $this->watermarkedDownloadName($photo), [
            'Cache-Control' => 'private, max-age=300',
        ]);
    }

    public function download(Request $request, Photo $photo): StreamedResponse
    {
        abort_unless($photo->event()->where('is_published', true)->exists(), 404);

        if ($this->userHasPaidAccess($request, $photo)) {
            abort_unless(Storage::disk('local')->exists($photo->original_path), 404);

            return Storage::disk('local')->download($photo->original_path, $photo->filename);
        }

        abort_unless(Storage::disk('local')->exists($photo->watermarked_path), 404);

        return Storage::disk('local')->download($photo->watermarked_path, $this->watermarkedDownloadName($photo));
    }

    private function userHasPaidAccess(Request $request, Photo $photo): bool
    {
        if (! $request->user()) {
            return false;
        }

        return OrderItem::query()
            ->where('photo_id', $photo->id)
            ->whereHas('order', fn ($query) => $query
                ->where('user_id', $request->user()->id)
                ->where('status', Order::STATUS_PAID))
            ->exists();
    }

    private function watermarkedDownloadName(Photo $photo): string
    {
        $extension = pathinfo($photo->watermarked_path, PATHINFO_EXTENSION)
            ?: pathinfo($photo->filename, PATHINFO_EXTENSION);
        $baseName = pathinfo($photo->filename, PATHINFO_FILENAME) ?: 'photo';

        return $baseName.'-watermark'.($extension ? '.'.$extension : '');
    }
}
