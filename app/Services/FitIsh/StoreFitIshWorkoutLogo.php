<?php

namespace App\Services\FitIsh;

use App\Models\FitIshWorkout;
use App\Models\OutgoingApiCall;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use League\Flysystem\FilesystemOperationFailed;

class StoreFitIshWorkoutLogo
{
    public function handle(FitIshWorkout $workout, string $url): void
    {
        $disk = (string) config('fit-ish.logo_disk', 'public');

        if (
            $workout->logo_url === $url
            && filled($workout->logo_path)
            && Storage::disk($disk)->exists($workout->logo_path)
        ) {
            return;
        }

        $started = hrtime(true);
        $status = null;
        $succeeded = false;
        $error = null;
        $responsePayload = null;

        try {
            $response = Http::connectTimeout(3)
                ->timeout((int) config('fit-ish.timeout', 20))
                ->get($url);

            $status = $response->status();
            $bytes = strlen($response->body());
            $contentType = (string) $response->header('Content-Type');
            $responsePayload = [
                'bytes' => $bytes,
                'content_type' => $contentType,
            ];

            if (! $response->successful() || $bytes === 0) {
                $error = 'Logo download failed.';

                return;
            }

            $extension = $this->extension($contentType, $url);
            $path = 'fit-ish/workouts/'.$workout->id.'.'.$extension;

            if (! Storage::disk($disk)->put($path, $response->body())) {
                $error = 'Logo storage write failed.';

                return;
            }

            $workout->forceFill([
                'logo_url' => $url,
                'logo_path' => $path,
            ])->save();

            $succeeded = true;
        } catch (RequestException $exception) {
            $status = $exception->response?->status() ?? $status;
            $error = $exception->getMessage();
        } catch (ConnectionException $exception) {
            $error = $exception->getMessage();
        } catch (FilesystemOperationFailed) {
            $error = 'Logo storage write failed.';
        } finally {
            OutgoingApiCall::query()->create([
                'source' => 'fit-ish',
                'method' => 'GET',
                'url' => $url,
                'status_code' => $status,
                'succeeded' => $succeeded,
                'duration_ms' => (int) ((hrtime(true) - $started) / 1_000_000),
                'error_message' => $error,
                'response' => $responsePayload,
            ]);
        }
    }

    private function extension(string $contentType, string $url): string
    {
        $type = Str::lower($contentType);

        return match (true) {
            str_contains($type, 'png') => 'png',
            str_contains($type, 'jpeg'), str_contains($type, 'jpg') => 'jpg',
            str_contains($type, 'webp') => 'webp',
            str_contains($type, 'gif') => 'gif',
            default => strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION) ?: 'png'),
        };
    }
}
