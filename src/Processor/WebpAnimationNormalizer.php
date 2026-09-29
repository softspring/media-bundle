<?php

declare(strict_types=1);

namespace Softspring\MediaBundle\Processor;

use RuntimeException;

class WebpAnimationNormalizer
{
    private const int ANMF_HEADER_SIZE = 16;

    private const int NO_BLEND_FLAG = 0x02;

    public function normalizeFullFrames(string $path): void
    {
        $file = fopen($path, 'r+b');
        if (false === $file) {
            throw new RuntimeException('Unable to open the animated WebP file.');
        }

        try {
            $fileSize = filesize($path);
            if (false === $fileSize || $fileSize < 12) {
                throw new RuntimeException('The animated WebP file is truncated.');
            }

            $header = $this->read($file, 12);
            if ('RIFF' !== substr($header, 0, 4) || 'WEBP' !== substr($header, 8, 4)) {
                throw new RuntimeException('The generated animation is not a valid WebP container.');
            }

            if ($this->unpackUint32(substr($header, 4, 4)) + 8 !== $fileSize) {
                throw new RuntimeException('The animated WebP RIFF size is invalid.');
            }

            $canvas = null;
            $hasAnimation = false;
            $frameFlagOffsets = [];
            $offset = 12;

            while ($offset < $fileSize) {
                if ($offset + 8 > $fileSize) {
                    throw new RuntimeException('The animated WebP chunk header is truncated.');
                }

                fseek($file, $offset);
                $chunkHeader = $this->read($file, 8);
                $chunkType = substr($chunkHeader, 0, 4);
                $chunkSize = $this->unpackUint32(substr($chunkHeader, 4, 4));
                $chunkDataOffset = $offset + 8;
                $nextOffset = $chunkDataOffset + $chunkSize + ($chunkSize % 2);

                if ($nextOffset > $fileSize || $nextOffset < $chunkDataOffset) {
                    throw new RuntimeException('The animated WebP chunk data is truncated.');
                }

                if ('VP8X' === $chunkType) {
                    if ($chunkSize < 10) {
                        throw new RuntimeException('The animated WebP canvas header is truncated.');
                    }

                    fseek($file, $chunkDataOffset);
                    $canvasHeader = $this->read($file, 10);
                    $canvas = [
                        'width' => $this->unpackUint24(substr($canvasHeader, 4, 3)) + 1,
                        'height' => $this->unpackUint24(substr($canvasHeader, 7, 3)) + 1,
                    ];
                } elseif ('ANIM' === $chunkType) {
                    $hasAnimation = true;
                } elseif ('ANMF' === $chunkType) {
                    if ($chunkSize < self::ANMF_HEADER_SIZE) {
                        throw new RuntimeException('The animated WebP frame header is truncated.');
                    }

                    fseek($file, $chunkDataOffset);
                    $frameHeader = $this->read($file, self::ANMF_HEADER_SIZE);
                    $frameFlagOffsets[] = $chunkDataOffset + 15;
                    $frame = [
                        'x' => $this->unpackUint24(substr($frameHeader, 0, 3)) * 2,
                        'y' => $this->unpackUint24(substr($frameHeader, 3, 3)) * 2,
                        'width' => $this->unpackUint24(substr($frameHeader, 6, 3)) + 1,
                        'height' => $this->unpackUint24(substr($frameHeader, 9, 3)) + 1,
                    ];

                    if (null === $canvas
                        || 0 !== $frame['x']
                        || 0 !== $frame['y']
                        || $canvas['width'] !== $frame['width']
                        || $canvas['height'] !== $frame['height']) {
                        throw new RuntimeException('Animated WebP normalization requires full-canvas frames.');
                    }
                }

                $offset = $nextOffset;
            }

            if (!$hasAnimation || count($frameFlagOffsets) < 2) {
                throw new RuntimeException('The generated WebP file is not animated.');
            }

            foreach ($frameFlagOffsets as $flagOffset) {
                fseek($file, $flagOffset);
                $flags = ord($this->read($file, 1));
                fseek($file, $flagOffset);

                if (1 !== fwrite($file, chr($flags | self::NO_BLEND_FLAG))) {
                    throw new RuntimeException('Unable to normalize the animated WebP frame.');
                }
            }
        } finally {
            fclose($file);
        }
    }

    /** @param resource $file */
    private function read($file, int $length): string
    {
        $data = fread($file, $length);
        if (false === $data || strlen($data) !== $length) {
            throw new RuntimeException('The animated WebP file is truncated.');
        }

        return $data;
    }

    private function unpackUint24(string $value): int
    {
        return ord($value[0]) | (ord($value[1]) << 8) | (ord($value[2]) << 16);
    }

    private function unpackUint32(string $value): int
    {
        $unpacked = unpack('Vvalue', $value);

        return $unpacked['value'];
    }
}
