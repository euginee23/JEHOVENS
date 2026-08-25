<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Saves uploaded photos to the public disk at a sane size.
 *
 * Guests photograph rooms on their phones, so a raw upload is routinely 4000px and several
 * megabytes — far more than a card rendered at ~400px needs. Everything wider than
 * MAX_WIDTH is downscaled once, on the way in, rather than shipped to every visitor.
 */
class PhotoStore
{
    /**
     * Widest a stored photo may be, in pixels.
     */
    public const MAX_WIDTH = 1600;

    /**
     * JPEG quality for the re-encoded file.
     */
    public const QUALITY = 82;

    /**
     * The disk photos are written to.
     */
    public const DISK = 'public';

    /**
     * Store an upload, resizing it if needed, and return its path on the public disk.
     */
    public static function store(UploadedFile $file, string $directory): string
    {
        $image = self::read($file);

        $width = imagesx($image);
        $height = imagesy($image);

        // Only ever shrink. Upscaling a small photo just wastes bytes.
        if ($width > self::MAX_WIDTH) {
            $targetWidth = self::MAX_WIDTH;
            // A very wide, very short source can round to zero, which GD rejects.
            $targetHeight = max(1, (int) round($height * $targetWidth / $width));

            $resized = imagecreatetruecolor($targetWidth, $targetHeight);
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

            imagedestroy($image);
            $image = $resized;
        }

        $path = trim($directory, '/').'/'.Str::uuid()->toString().'.jpg';

        ob_start();
        imagejpeg($image, null, self::QUALITY);
        $contents = (string) ob_get_clean();

        imagedestroy($image);

        Storage::disk(self::DISK)->put($path, $contents);

        return $path;
    }

    /**
     * Remove a stored photo from disk.
     */
    public static function delete(string $path): void
    {
        Storage::disk(self::DISK)->delete($path);
    }

    /**
     * Decode an upload into a GD image, whatever format it arrived in.
     *
     * @return \GdImage
     */
    protected static function read(UploadedFile $file)
    {
        $contents = file_get_contents($file->getRealPath());

        if ($contents === false) {
            throw new RuntimeException('Could not read the uploaded photo.');
        }

        $image = @imagecreatefromstring($contents);

        if ($image === false) {
            throw new RuntimeException('That file is not an image we can process.');
        }

        // Flatten transparency onto white so PNG and WebP uploads survive the JPEG re-encode.
        $flattened = imagecreatetruecolor(imagesx($image), imagesy($image));
        $white = imagecolorallocate($flattened, 255, 255, 255);

        if ($white !== false) {
            imagefill($flattened, 0, 0, $white);
        }

        imagecopy($flattened, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));

        imagedestroy($image);

        return $flattened;
    }
}
