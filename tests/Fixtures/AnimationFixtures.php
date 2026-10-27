<?php

declare(strict_types=1);

namespace Softspring\MediaBundle\Tests\Fixtures;

/** Small real PNG/APNG files, without a dependency on an image extension. */
final class AnimationFixtures
{
    public static function png(int $frames = 1, bool $separateDefaultImage = false): string
    {
        $png = "\x89PNG\r\n\x1A\n".self::chunk('IHDR', pack('NNCCCCC', 8, 6, 8, 6, 0, 0, 0));
        if ($frames > 1) {
            $png .= self::chunk('acTL', pack('NN', $frames, 0));
        }
        if ($separateDefaultImage) {
            $png .= self::chunk('IDAT', gzcompress(str_repeat("\x00".str_repeat("\x00\x00\xff\x80", 8), 6)));
        }
        $sequence = 0;
        for ($frame = 0; $frame < $frames; ++$frame) {
            if ($frames > 1) {
                $png .= self::chunk('fcTL', pack('NNNNNnnCC', $sequence++, 8, 6, 0, 0, $frame + 1, 10, 0, 0));
            }
            $pixel = $frame % 2 ? "\x00\xff\x00\x80" : "\xff\x00\x00\x80";
            $data = gzcompress(str_repeat("\x00".str_repeat($pixel, 8), 6));
            $png .= 0 === $frame && !$separateDefaultImage ? self::chunk('IDAT', $data) : self::chunk('fdAT', pack('N', $sequence++).$data);
        }

        return $png.self::chunk('IEND', '');
    }

    public static function gif(int $frames = 2): string
    {
        $header = 'GIF89a'.pack('vvCCC', 1, 1, 0x80, 0, 0)."\x00\x00\x00\xff\xff\xff";
        $frame = "\x21\xf9\x04\x00\x01\x00\x00\x00\x2c".pack('vvvvC', 0, 0, 1, 1, 0)."\x02\x02\x44\x01\x00";

        return $header.str_repeat($frame, $frames)."\x3b";
    }

    private static function chunk(string $type, string $data): string
    {
        return pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
    }
}
