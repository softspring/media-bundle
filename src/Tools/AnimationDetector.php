<?php

declare(strict_types=1);

namespace Softspring\MediaBundle\Tools;

use RuntimeException;
use Softspring\MediaBundle\Model\MediaInterface;
use Softspring\MediaBundle\Model\MediaVersionInterface;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Process\Process;

/** Detect actual animation, independently of the file extension or media type. */
class AnimationDetector
{
    public function __construct(
        private readonly string $ffprobeBinary = 'ffprobe',
        private readonly int $ffmpegTimeout = 300,
    ) {
    }

    /** Stored during upload or regeneration, so rendering never downloads a file. */
    public function isAnimatedMedia(MediaInterface $media): bool
    {
        $animated = $media->getMetadataField('animated');
        if (is_bool($animated)) {
            return $animated;
        }
        $original = $media->getVersion('_original');
        if ($original?->getUpload() instanceof File) {
            return $this->isAnimated($original->getUpload()->getRealPath());
        }

        // Older APNG uploads were already identified by their MIME type.
        return 'image/apng' === $original?->getFileMimeType();
    }

    public function isAnimatedVersion(MediaVersionInterface $version): bool
    {
        $file = $version->getUpload() ?? $version->getOriginalVersion()?->getUpload();

        return $file instanceof File && $this->isAnimated($file->getRealPath());
    }

    public function isAnimated(string $path): bool
    {
        $stream = @fopen($path, 'rb');
        if (false === $stream) {
            throw new RuntimeException('Cannot read the image to detect animation.');
        }

        try {
            $header = fread($stream, 12);
            if (str_starts_with($header, "\x89PNG\r\n\x1A\n")) {
                fseek($stream, 8);
                while (8 === strlen($chunk = fread($stream, 8))) {
                    $size = unpack('N', substr($chunk, 0, 4))[1];
                    $type = substr($chunk, 4, 4);
                    if ('acTL' === $type && $size >= 8) {
                        $frames = fread($stream, 4);

                        return 4 === strlen($frames) && unpack('N', $frames)[1] > 1;
                    }
                    if ('IDAT' === $type || 'IEND' === $type) {
                        return false;
                    }
                    if (0 !== fseek($stream, $size + 4, SEEK_CUR)) {
                        break;
                    }
                }

                return false;
            }

            if (in_array(substr($header, 0, 6), ['GIF87a', 'GIF89a'], true)) {
                return $this->gifHasMultipleFrames($stream);
            }

            if ('RIFF' === substr($header, 0, 4) && 'WEBP' === substr($header, 8, 4)) {
                $frames = 0;
                while (8 === strlen($chunk = fread($stream, 8))) {
                    $size = unpack('V', substr($chunk, 4, 4))[1];
                    if ('ANMF' === substr($chunk, 0, 4) && ++$frames > 1) {
                        return true;
                    }
                    if (0 !== fseek($stream, $size + ($size % 2), SEEK_CUR)) {
                        break;
                    }
                }

                return false;
            }

            // AVIF sequences advertise the avis brand. Static AVIF alpha streams
            // are separate images, not extra animation frames.
            if ('ftyp' === substr($header, 4, 4)) {
                $size = unpack('N', substr($header, 0, 4))[1];
                if ($size < 16 || $size > 65536) {
                    return false;
                }
                fseek($stream, 8);
                $brands = fread($stream, $size - 8);
                $sequence = 'avis' === substr($brands, 0, 4);
                for ($offset = 8; !$sequence && $offset + 4 <= strlen($brands); $offset += 4) {
                    $sequence = 'avis' === substr($brands, $offset, 4);
                }

                return $sequence && $this->avifHasMultipleFrames($path);
            }

            return false;
        } finally {
            fclose($stream);
        }
    }

    /** @param resource $stream */
    private function gifHasMultipleFrames($stream): bool
    {
        fseek($stream, 10);
        $packed = fread($stream, 1);
        if ('' === $packed) {
            return false;
        }
        $flags = ord($packed);
        fseek($stream, 13 + (($flags & 0x80) ? 3 * (2 << ($flags & 7)) : 0));
        $frames = 0;
        while ('' !== ($marker = fread($stream, 1))) {
            if ("\x3b" === $marker) {
                return false;
            }
            if ("\x21" === $marker) {
                fread($stream, 1); // extension label
            } elseif ("\x2c" === $marker) {
                if (++$frames > 1) {
                    return true;
                }
                $descriptor = fread($stream, 9);
                if (9 !== strlen($descriptor)) {
                    return false;
                }
                $flags = ord($descriptor[8]);
                if ($flags & 0x80) {
                    fseek($stream, 3 * (2 << ($flags & 7)), SEEK_CUR);
                }
                fread($stream, 1); // LZW minimum code size
            } else {
                return false;
            }
            do {
                $byte = fread($stream, 1);
                if ('' === $byte) {
                    return false;
                }
                $size = ord($byte);
                if ($size > 0) {
                    fseek($stream, $size, SEEK_CUR);
                }
            } while ($size > 0);
        }

        return false;
    }

    private function avifHasMultipleFrames(string $path): bool
    {
        $process = new Process([$this->ffprobeBinary, '-v', 'error', '-select_streams', 'v', '-count_frames', '-show_entries', 'stream=nb_frames,nb_read_frames', '-of', 'json', $path]);
        $process->setTimeout($this->ffmpegTimeout);
        $process->mustRun();
        $probe = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        foreach ($probe['streams'] ?? [] as $stream) {
            if (max((int) ($stream['nb_frames'] ?? 0), (int) ($stream['nb_read_frames'] ?? 0)) > 1) {
                return true;
            }
        }

        return false;
    }
}
