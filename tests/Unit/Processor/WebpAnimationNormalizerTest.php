<?php

declare(strict_types=1);

namespace Softspring\MediaBundle\Tests\Unit\Processor;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Softspring\MediaBundle\Processor\WebpAnimationNormalizer;

class WebpAnimationNormalizerTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir().'/webp-animation-normalizer-'.uniqid('', true).'.webp';
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
    }

    public function testMarksFullCanvasFramesAsNoBlendWithoutChangingFileSize(): void
    {
        $contents = $this->createAnimatedWebp([
            ['width' => 480, 'height' => 400, 'flags' => 0],
            ['width' => 480, 'height' => 400, 'flags' => 1],
        ]);
        file_put_contents($this->path, $contents);

        $normalizer = new WebpAnimationNormalizer();
        $normalizer->normalizeFullFrames($this->path);
        $normalizer->normalizeFullFrames($this->path);

        $normalized = file_get_contents($this->path);
        $this->assertIsString($normalized);
        $this->assertSame(strlen($contents), strlen($normalized));
        $this->assertSame([2, 3], $this->readFrameFlags($normalized));
    }

    public function testRejectsPartialFramesWithoutChangingTheFile(): void
    {
        $contents = $this->createAnimatedWebp([
            ['width' => 480, 'height' => 400, 'flags' => 0],
            ['width' => 478, 'height' => 400, 'flags' => 0],
        ]);
        file_put_contents($this->path, $contents);

        try {
            (new WebpAnimationNormalizer())->normalizeFullFrames($this->path);
            $this->fail('Partial WebP frames must be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Animated WebP normalization requires full-canvas frames.', $exception->getMessage());
        }

        $this->assertSame($contents, file_get_contents($this->path));
    }

    public function testRejectsTruncatedChunks(): void
    {
        $contents = $this->createAnimatedWebp([
            ['width' => 480, 'height' => 400, 'flags' => 0],
            ['width' => 480, 'height' => 400, 'flags' => 0],
        ]);
        $contents = substr($contents, 0, -1);
        file_put_contents($this->path, $contents);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The animated WebP RIFF size is invalid.');

        (new WebpAnimationNormalizer())->normalizeFullFrames($this->path);
    }

    public function testRejectsStaticWebp(): void
    {
        $contents = $this->riff($this->chunk('VP8X', $this->vp8x(480, 400)));
        file_put_contents($this->path, $contents);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The generated WebP file is not animated.');

        (new WebpAnimationNormalizer())->normalizeFullFrames($this->path);
    }

    /** @param list<array{width: int, height: int, flags: int}> $frames */
    private function createAnimatedWebp(array $frames): string
    {
        $chunks = $this->chunk('VP8X', $this->vp8x(480, 400));
        $chunks .= $this->chunk('ANIM', str_repeat("\0", 6));

        foreach ($frames as $frame) {
            $frameHeader = $this->uint24(0).$this->uint24(0);
            $frameHeader .= $this->uint24($frame['width'] - 1).$this->uint24($frame['height'] - 1);
            $frameHeader .= $this->uint24(42).chr($frame['flags']);
            $chunks .= $this->chunk('ANMF', $frameHeader);
        }

        return $this->riff($chunks);
    }

    private function riff(string $chunks): string
    {
        return 'RIFF'.pack('V', strlen($chunks) + 4).'WEBP'.$chunks;
    }

    private function chunk(string $type, string $data): string
    {
        return $type.pack('V', strlen($data)).$data.(strlen($data) % 2 !== 0 ? "\0" : '');
    }

    private function vp8x(int $width, int $height): string
    {
        return chr(0x02)."\0\0\0".$this->uint24($width - 1).$this->uint24($height - 1);
    }

    private function uint24(int $value): string
    {
        return chr($value & 0xff).chr(($value >> 8) & 0xff).chr(($value >> 16) & 0xff);
    }

    /** @return list<int> */
    private function readFrameFlags(string $contents): array
    {
        $flags = [];
        $offset = 12;

        while ($offset < strlen($contents)) {
            $chunkSize = unpack('Vvalue', substr($contents, $offset + 4, 4));
            $this->assertIsArray($chunkSize);
            if ('ANMF' === substr($contents, $offset, 4)) {
                $flags[] = ord($contents[$offset + 8 + 15]);
            }
            $offset += 8 + $chunkSize['value'] + ($chunkSize['value'] % 2);
        }

        return $flags;
    }
}
