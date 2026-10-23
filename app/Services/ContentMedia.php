<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ContentMedia
{
    /** Re-encode public editorial images to remove metadata and bound download size. */
    public function image(UploadedFile $file): string
    {
        $image = @imagecreatefromstring($file->getContent());
        if (! $image) {
            throw ValidationException::withMessages(['featured_upload' => 'Please upload a readable JPEG, PNG or WebP image.']);
        }
        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, 1800 / max($width, $height));
        $resized = imagecreatetruecolor(max(1, (int) ($width * $scale)), max(1, (int) ($height * $scale)));
        imagefill($resized, 0, 0, imagecolorallocate($resized, 255, 255, 255));
        imagecopyresampled($resized, $image, 0, 0, 0, 0, imagesx($resized), imagesy($resized), $width, $height);
        ob_start();
        imagejpeg($resized, null, 85);
        $bytes = ob_get_clean();
        $path = 'cms/images/'.Str::uuid().'.jpg';
        if (! Storage::disk('local')->put($path, $bytes)) {
            throw new \RuntimeException('Unable to store the editorial image.');
        }
        imagedestroy($image);
        imagedestroy($resized);

        return $path;
    }

    public function video(UploadedFile $file): string
    {
        return $file->storeAs('cms/videos', Str::uuid().'.mp4', 'local');
    }

    public static function youtubeId(?string $url): ?string
    {
        if (! $url) {
            return null;
        }
        $parts = parse_url($url);
        if (! $parts || ($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['pass']) || isset($parts['port'])) {
            return null;
        }
        $host = strtolower($parts['host'] ?? '');
        $path = $parts['path'] ?? '';
        $id = null;
        if ($host === 'youtu.be') {
            $id = ltrim($path, '/');
        } elseif (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com'], true)) {
            if ($path === '/watch') {
                parse_str($parts['query'] ?? '', $query);
                $id = $query['v'] ?? null;
            } elseif (preg_match('~^/(?:embed|shorts)/([A-Za-z0-9_-]{11})$~D', $path, $matches)) {
                $id = $matches[1];
            }
        }

        return is_string($id) && preg_match('/^[A-Za-z0-9_-]{11}$/D', $id) ? $id : null;
    }
}
