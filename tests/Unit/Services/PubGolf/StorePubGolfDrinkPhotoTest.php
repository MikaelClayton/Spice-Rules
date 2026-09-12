<?php

namespace Tests\Unit\Services\PubGolf;

use App\Services\PubGolf\StorePubGolfDrinkPhoto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StorePubGolfDrinkPhotoTest extends TestCase
{
    public function test_it_stores_a_jpeg_scaled_to_the_max_edge(): void
    {
        Storage::fake('public');
        $photo = UploadedFile::fake()->image('castle.png', 2000, 1000);

        $path = app(StorePubGolfDrinkPhoto::class)->handle($photo);

        Storage::disk('public')->assertExists($path);
        $this->assertStringEndsWith('.jpg', $path);

        $size = getimagesizefromstring((string) Storage::disk('public')->get($path));

        $this->assertNotFalse($size);
        $this->assertSame(1280, $size[0]);
        $this->assertSame(640, $size[1]);
        $this->assertSame(IMAGETYPE_JPEG, $size[2]);
    }

    public function test_it_rejects_heic_photos(): void
    {
        Storage::fake('public');
        $photo = UploadedFile::fake()->create('pint.heic', 200, 'image/heic');

        try {
            app(StorePubGolfDrinkPhoto::class)->handle($photo);
            $this->fail('HEIC photos should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['Use a JPEG, PNG, or WebP photo. HEIC is not allowed.'],
                $exception->errors()['photo'],
            );
        }

        $this->assertSame([], Storage::disk('public')->allFiles());
    }
}
