<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use League\Flysystem\FilesystemException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/** Store validated assets under generated public paths without using client filenames. */
class ContentMediaService
{
    /** @return array{path: string, url: string, mime_type: string, size: int} Decode and re-encode an already validated icon. */
    public function icon(UploadedFile $file): array
    {
        if (! function_exists('imagecreatefromstring')) {
            throw new ServiceUnavailableHttpException;
        }
        $image = @imagecreatefromstring($file->get());
        if ($image === false) {
            throw ValidationException::withMessages(['file' => 'The icon could not be decoded.']);
        }
        imagesavealpha($image, true);
        $stream = tmpfile();
        if ($stream === false) {
            throw new ServiceUnavailableHttpException;
        }

        try {
            if (! imagepng($image, $stream, 6)) {
                throw new ServiceUnavailableHttpException;
            }
            $size = ftell($stream);
            rewind($stream);
            $path = 'icons/'.Str::uuid().'.png';
            try {
                if (! Storage::disk('public')->put($path, $stream, 'public')) {
                    throw new ServiceUnavailableHttpException;
                }
            } catch (FilesystemException) {
                throw new ServiceUnavailableHttpException;
            }

            return $this->metadata($path, 'image/png', (int) $size);
        } finally {
            fclose($stream);
        }
    }

    /** @return array{path: string, url: string, mime_type: string, size: int} Store validated audio using a detected-type extension. */
    public function audio(UploadedFile $file): array
    {
        $mime = $file->getMimeType();
        $extension = match ($mime) {
            'audio/mpeg', 'audio/mp3' => 'mp3',
            'audio/mp4', 'audio/x-m4a' => 'm4a',
            'audio/ogg' => 'ogg',
            'audio/wav', 'audio/x-wav', 'audio/vnd.wave' => 'wav',
            default => throw ValidationException::withMessages(['file' => 'Unsupported audio type.']),
        };
        $name = Str::uuid().'.'.$extension;
        try {
            $path = $file->storePubliclyAs('audio', $name, 'public');
        } catch (FilesystemException) {
            throw new ServiceUnavailableHttpException;
        }
        if ($path === false) {
            throw new ServiceUnavailableHttpException;
        }

        return $this->metadata($path, $mime, (int) $file->getSize());
    }

    /** @return array{path: string, url: string, mime_type: string, size: int} Return only public asset information. */
    private function metadata(string $path, string $mime, int $size): array
    {
        return ['path' => $path, 'url' => Storage::disk('public')->url($path), 'mime_type' => $mime, 'size' => $size];
    }
}
