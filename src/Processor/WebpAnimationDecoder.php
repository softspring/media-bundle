<?php

declare(strict_types=1);

namespace Softspring\MediaBundle\Processor;

use Imagick;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

/** Lossless bridge for FFmpeg builds without animated WebP decoding. */
class WebpAnimationDecoder
{
    public function decode(string $path, string $ffmpegBinary, int $timeout, ?int $maxFrames = null): string
    {
        if (!class_exists(Imagick::class)) {
            throw new RuntimeException('Imagick is required to decode animated WebP uploads.');
        }

        $directory = sys_get_temp_dir().'/media-webp-'.bin2hex(random_bytes(12));
        if (!mkdir($directory, 0700)) {
            throw new RuntimeException('Cannot create a temporary directory for WebP decoding.');
        }
        $output = $directory.'.png';
        $images = new Imagick();
        $frames = null;
        try {
            $images->readImage($path);
            $count = $images->getNumberImages();
            if (null !== $maxFrames && $count > $maxFrames) {
                throw new RuntimeException(sprintf('Animation has %d frames; configured maximum is %d.', $count, $maxFrames));
            }
            $iterations = $images->getImageIterations();
            $frames = $images->coalesceImages();
            $manifest = "ffconcat version 1.0\n";
            $lastDelay = '1/100';
            foreach ($frames as $index => $frame) {
                $ticks = max(1, $frame->getImageTicksPerSecond());
                $delay = max(1, $frame->getImageDelay());
                $lastDelay = $delay.'/'.$ticks;
                $name = sprintf('frame-%06d.png', $index);
                $frame->setImageFormat('png32');
                $frame->writeImage($directory.'/'.$name);
                $manifest .= "file '$name'\noption framerate 1000\nduration ".sprintf('%.9F', $delay / $ticks)."\n";
            }
            file_put_contents($directory.'/frames.txt', $manifest);
            $process = new Process([
                $ffmpegBinary, '-v', 'error', '-y', '-f', 'concat', '-safe', '0',
                '-i', $directory.'/frames.txt', '-fps_mode', 'vfr', '-enc_time_base', '1:1000',
                '-plays', (string) $iterations, '-final_delay', $lastDelay, '-pix_fmt', 'rgba', '-f', 'apng', $output,
            ]);
            $process->setTimeout($timeout);
            $process->mustRun();

            return $output;
        } catch (Throwable $exception) {
            @unlink($output);
            throw $exception;
        } finally {
            $images->clear();
            $frames?->clear();
            foreach (glob($directory.'/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($directory);
        }
    }
}
