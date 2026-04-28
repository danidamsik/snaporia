<?php

namespace App\Http\Controllers;

use App\Models\Photo;
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
}
