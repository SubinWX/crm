<?php

namespace App\Support;

/** Small GD helper used for poster thumbnails. */
class ImageThumb
{
    /** Returns JPEG bytes, or null when the image cannot be read. */
    public static function make(string $sourcePath, int $maxSide = 640): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }
        $image = @imagecreatefromstring((string) @file_get_contents($sourcePath));
        if (! $image) {
            return null;
        }

        [$w, $h] = [imagesx($image), imagesy($image)];
        $scale = min(1, $maxSide / max($w, $h));
        [$tw, $th] = [max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale))];

        $thumb = imagecreatetruecolor($tw, $th);
        imagefill($thumb, 0, 0, imagecolorallocate($thumb, 255, 255, 255));
        imagecopyresampled($thumb, $image, 0, 0, 0, 0, $tw, $th, $w, $h);

        ob_start();
        imagejpeg($thumb, null, 82);
        $bytes = ob_get_clean();
        imagedestroy($image);
        imagedestroy($thumb);

        return $bytes ?: null;
    }
}
