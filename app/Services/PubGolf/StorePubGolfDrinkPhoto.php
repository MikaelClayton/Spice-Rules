<?php

namespace App\Services\PubGolf;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class StorePubGolfDrinkPhoto
{
    public function handle(UploadedFile $photo, ?string $directory = null): string
    {
        $this->rejectHeic($photo);

        $path = $photo->getRealPath();

        if (! is_string($path) || $path === '') {
            throw ValidationException::withMessages([
                'photo' => 'That photo could not be read.',
            ]);
        }

        $contents = file_get_contents($path);

        if ($contents === false || $contents === '') {
            throw ValidationException::withMessages([
                'photo' => 'That photo could not be read.',
            ]);
        }

        $image = @imagecreatefromstring($contents);

        if (! $image instanceof GdImage) {
            throw ValidationException::withMessages([
                'photo' => 'Use a JPEG, PNG, or WebP photo. HEIC is not allowed.',
            ]);
        }

        $image = $this->orient($image, $path);
        $image = $this->scaleDown($image);

        ob_start();
        $encoded = imagejpeg($image, null, (int) config('pub-golf.photo_quality'));
        $jpeg = ob_get_clean();
        imagedestroy($image);

        if ($encoded !== true || ! is_string($jpeg) || $jpeg === '') {
            throw new RuntimeException('Photo could not be compressed.');
        }

        $storedPath = ($directory ?? (string) config('pub-golf.photo_directory')).'/'.Str::uuid()->toString().'.jpg';

        Storage::disk((string) config('pub-golf.photo_disk'))->put($storedPath, $jpeg);

        return $storedPath;
    }

    private function rejectHeic(UploadedFile $photo): void
    {
        $name = Str::lower($photo->getClientOriginalName());
        $detectedMime = Str::lower((string) $photo->getMimeType());
        $clientMime = Str::lower($photo->getClientMimeType());

        if (
            str_ends_with($name, '.heic')
            || str_ends_with($name, '.heif')
            || str_contains($detectedMime, 'heic')
            || str_contains($detectedMime, 'heif')
            || str_contains($clientMime, 'heic')
            || str_contains($clientMime, 'heif')
        ) {
            throw ValidationException::withMessages([
                'photo' => 'Use a JPEG, PNG, or WebP photo. HEIC is not allowed.',
            ]);
        }
    }

    private function orient(GdImage $image, string $path): GdImage
    {
        $exif = @exif_read_data($path);
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;

        $rotated = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => null,
        };

        if ($rotated instanceof GdImage) {
            imagedestroy($image);

            return $rotated;
        }

        return $image;
    }

    private function scaleDown(GdImage $image): GdImage
    {
        $maxEdge = (int) config('pub-golf.photo_max_edge');
        $width = imagesx($image);
        $height = imagesy($image);
        $longest = max($width, $height);

        if ($longest <= $maxEdge) {
            return $image;
        }

        $scale = $maxEdge / $longest;
        $scaled = imagescale($image, (int) round($width * $scale));
        imagedestroy($image);

        if (! $scaled instanceof GdImage) {
            throw new RuntimeException('Photo could not be resized.');
        }

        return $scaled;
    }
}
