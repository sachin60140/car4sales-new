<?php

namespace App\Domain\Documents\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stores uploaded files/media on the private disk with a deterministic path,
 * generating an image thumbnail where possible. Files are never public — access
 * is always via temporary signed URLs (see signedUrl()).
 */
class MediaUploadService
{
    /**
     * @return array{path: string, thumbnail_path: ?string, original_name: string, mime_type: string, size_bytes: int}
     */
    public function store(UploadedFile $file, string $directory): array
    {
        $filename = Str::uuid().'.'.$file->getClientOriginalExtension();
        $path = trim($directory, '/')."/{$filename}";

        Storage::disk('private')->putFileAs(trim($directory, '/'), $file, $filename);

        $thumbnailPath = $this->makeThumbnail($file, $directory, $filename);

        return [
            'path' => $path,
            'thumbnail_path' => $thumbnailPath,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
        ];
    }

    /**
     * Temporary signed URL for private files. Falls back to a streamed route URL
     * on disks that don't support signed URLs (e.g. local).
     */
    public function signedUrl(string $path, int $minutes = 10): string
    {
        $disk = Storage::disk('private');

        try {
            return $disk->temporaryUrl($path, now()->addMinutes($minutes));
        } catch (\Throwable) {
            return url('/files/'.rawurlencode($path));
        }
    }

    private function makeThumbnail(UploadedFile $file, string $directory, string $filename): ?string
    {
        if (! str_starts_with($file->getClientMimeType(), 'image/') || ! extension_loaded('gd')) {
            return null;
        }

        try {
            $contents = file_get_contents($file->getRealPath());
            $image = imagecreatefromstring($contents);

            if ($image === false) {
                return null;
            }

            $width = imagesx($image);
            $height = imagesy($image);
            $targetWidth = 320;
            $targetHeight = (int) round($height * ($targetWidth / max($width, 1)));

            $thumb = imagecreatetruecolor($targetWidth, $targetHeight);
            imagecopyresampled($thumb, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

            ob_start();
            imagejpeg($thumb, null, 75);
            $data = ob_get_clean();
            imagedestroy($image);
            imagedestroy($thumb);

            $thumbPath = trim($directory, '/').'/thumbnails/'.pathinfo($filename, PATHINFO_FILENAME).'.jpg';
            Storage::disk('private')->put($thumbPath, $data);

            return $thumbPath;
        } catch (\Throwable) {
            return null;
        }
    }
}
