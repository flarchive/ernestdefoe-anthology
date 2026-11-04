<?php

namespace Ernestdefoe\Anthology;

use Flarum\Foundation\ValidationException;
use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Psr\Http\Message\UploadedFileInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Cover images. The bytes are sniffed, decoded and re-encoded to WebP (so
 * nothing but pixels survives: no SVG, no script, no metadata), scaled down to
 * a sane size with their shape kept (book covers are tall, banners are wide)
 * and stored under a random name with the forum's public assets.
 */
class Covers
{
    public const MAX_BYTES = 5 * 1024 * 1024;
    public const MAX_SIDE = 1200;
    // Decoding needs 4 bytes a pixel whatever the file size: a few hundred KB of
    // PNG can claim 20000 × 20000 and take gigabytes. Read the header first.
    public const MAX_PIXELS = 40_000_000;
    private const DIR = 'anthology/';

    public function __construct(private Factory $filesystems, private ImageManager $images, private TranslatorInterface $translator)
    {
    }

    public function url(Series $series): ?string
    {
        return $series->cover_path ? $this->filesystems->disk('flarum-assets')->url($series->cover_path) : null;
    }

    public function store(UploadedFileInterface $file): string
    {
        if ($file->getError() !== UPLOAD_ERR_OK || $file->getSize() === 0) {
            $this->fail('upload_failed');
        }
        if ($file->getSize() > self::MAX_BYTES) {
            $this->fail('cover_too_big');
        }

        $bytes = (string) $file->getStream();
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)) {
            $this->fail('not_an_image');
        }

        $size = @getimagesizefromstring($bytes);
        if (! $size || $size[0] < 1 || $size[1] < 1 || $size[0] * $size[1] > self::MAX_PIXELS) {
            $this->fail('cover_too_big');
        }

        try {
            $image = $this->images->read($bytes);
            $image->scaleDown(width: self::MAX_SIDE, height: self::MAX_SIDE * 2);
            $encoded = (string) $image->toWebp(82);
        } catch (\Throwable) {
            $this->fail('not_an_image');
        }

        $path = self::DIR.Str::random(32).'.webp';
        $this->filesystems->disk('flarum-assets')->put($path, $encoded);

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path && str_starts_with($path, self::DIR)) {
            $this->filesystems->disk('flarum-assets')->delete($path);
        }
    }

    private function fail(string $key): never
    {
        throw new ValidationException(['cover' => $this->translator->trans('ernestdefoe-anthology.api.'.$key)]);
    }
}
