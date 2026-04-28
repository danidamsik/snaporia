<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use RuntimeException;

class WatermarkService
{
    public function createPreview(string $sourcePath, string $targetPath, string $text, int $opacity = 25): void
    {
        $absoluteSourcePath = Storage::disk('local')->path($sourcePath);
        $imageSize = getimagesize($absoluteSourcePath);

        if ($imageSize === false) {
            throw new RuntimeException('File gambar tidak dapat dibaca.');
        }

        [$width, $height] = $imageSize;
        $mimeType = $imageSize['mime'];
        $sourceImage = $this->createImage($absoluteSourcePath, $mimeType);

        if (! $sourceImage) {
            throw new RuntimeException('Format gambar tidak didukung.');
        }

        [$previewWidth, $previewHeight] = $this->previewDimensions($width, $height);
        $preview = imagecreatetruecolor($previewWidth, $previewHeight);

        imagealphablending($preview, true);
        imagesavealpha($preview, true);
        imagecopyresampled($preview, $sourceImage, 0, 0, 0, 0, $previewWidth, $previewHeight, $width, $height);
        imagedestroy($sourceImage);

        $this->applyRepeatedWatermark($preview, $previewWidth, $previewHeight, $text, $opacity);

        Storage::disk('local')->makeDirectory(dirname($targetPath));
        $absoluteTargetPath = Storage::disk('local')->path($targetPath);

        if (! imagejpeg($preview, $absoluteTargetPath, 80)) {
            imagedestroy($preview);

            throw new RuntimeException('Preview watermark gagal disimpan.');
        }

        imagedestroy($preview);
    }

    private function createImage(string $path, string $mimeType): mixed
    {
        return match ($mimeType) {
            'image/jpeg' => imagecreatefromjpeg($path),
            'image/png' => imagecreatefrompng($path),
            'image/webp' => function_exists('imagecreatefromwebp') ? imagecreatefromwebp($path) : false,
            default => false,
        };
    }

    private function previewDimensions(int $width, int $height): array
    {
        $maxWidth = 1600;

        if ($width <= $maxWidth) {
            return [$width, $height];
        }

        return [$maxWidth, (int) round($height * ($maxWidth / $width))];
    }

    private function applyRepeatedWatermark(mixed $image, int $width, int $height, string $text, int $opacity): void
    {
        $alpha = max(0, min(127, 127 - (int) round(127 * (max(0, min(100, $opacity)) / 100))));
        $color = imagecolorallocatealpha($image, 255, 255, 255, $alpha);
        $shadow = imagecolorallocatealpha($image, 17, 24, 39, min(127, $alpha + 25));
        $font = 5;
        $stepX = 240;
        $stepY = 160;

        for ($y = -$stepY; $y < $height + $stepY; $y += $stepY) {
            for ($x = -$stepX; $x < $width + $stepX; $x += $stepX) {
                imagestringup($image, $font, $x + 1, $y + 1, $text, $shadow);
                imagestringup($image, $font, $x, $y, $text, $color);
            }
        }
    }
}
