<?php

declare(strict_types=1);

namespace Softspring\MediaBundle\Processor;

use JsonException;
use RuntimeException;
use Softspring\MediaBundle\Model\MediaVersionInterface;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Process\Process;
use Throwable;

class FfmpegProcessor implements ProcessorInterface
{
    private const array SUPPORTED_SOURCE_MIME_TYPES = [
        'image/apng',
        'image/avif',
        'image/gif',
        'image/png',
        'image/webp',
    ];

    private const array MIME_TYPE_FORMATS = [
        'image/apng' => 'apng',
        'image/avif' => 'avif',
        'image/gif' => 'gif',
        'image/png' => 'apng',
        'image/webp' => 'webp',
    ];

    public function __construct(
        protected string $ffmpegBinary,
        protected string $ffprobeBinary,
        protected int $ffmpegTimeout,
    ) {
    }

    public static function getPriority(): int
    {
        return 10;
    }

    public function supports(MediaVersionInterface $version): bool
    {
        if ('_original' === $version->getVersion()) {
            return false;
        }

        if (isset($version->getOptions()['upload_requirements'])) {
            return false;
        }

        if (!$version->getOriginalVersion() instanceof MediaVersionInterface) {
            return false;
        }

        if (!$version->getOptions()) {
            throw new RuntimeException('Processor support method requires version options are initialized');
        }

        if (!($version->getOptions()['animated'] ?? false)) {
            return false;
        }

        if (!in_array($version->getOriginalVersion()->getFileMimeType(), self::SUPPORTED_SOURCE_MIME_TYPES, true)) {
            return false;
        }

        return in_array($this->getTargetFormat($version), ['apng', 'avif', 'gif', 'webp'], true);
    }

    public function process(MediaVersionInterface $version): void
    {
        if ($version->getUpload() instanceof UploadedFile) {
            return;
        }

        if (!$version->getUpload() instanceof File) {
            return;
        }

        $inputPath = $version->getUpload()->getRealPath();
        $options = $version->getOptions();
        $animationOptions = $options['animation'] ?? [];
        $metadata = $this->probeAnimation($inputPath);

        $this->validateAnimation($metadata, $animationOptions);

        [$width, $height] = $this->calculateDimensions($metadata, $options);
        $targetFormat = $this->getTargetFormat($version);
        $outputPath = $this->createTemporaryOutputPath($targetFormat);
        $command = $this->buildCommand($inputPath, $outputPath, $targetFormat, $width, $height, $options, $animationOptions, $metadata);

        try {
            $this->runFfmpeg($command);

            $outputSize = is_file($outputPath) ? filesize($outputPath) : false;
            if (false === $outputSize || 0 === $outputSize) {
                throw new RuntimeException('FFmpeg did not create the expected animated media file.');
            }
        } catch (Throwable $exception) {
            @unlink($outputPath);

            throw $exception;
        }

        @unlink($inputPath);

        $version->setUpload(new File($outputPath));
        $version->setWidth($width);
        $version->setHeight($height);
    }

    /**
     * @return array{width: int, height: int, frames: int, duration: float|null, alpha: 'packed'|'stream'|null, stream: int, alpha_stream: int|null}
     */
    protected function probeAnimation(string $path): array
    {
        $process = new Process([
            $this->ffprobeBinary,
            '-v',
            'error',
            '-select_streams',
            'v',
            '-count_frames',
            '-show_entries',
            'stream=width,height,pix_fmt,nb_frames,nb_read_frames,duration:format=duration',
            '-of',
            'json',
            $path,
        ]);
        $process->setTimeout($this->ffmpegTimeout);
        $process->mustRun();

        return $this->parseProbeOutput($process->getOutput());
    }

    /**
     * @return array{width: int, height: int, frames: int, duration: float|null, alpha: 'packed'|'stream'|null, stream: int, alpha_stream: int|null}
     *
     * @throws JsonException
     */
    protected function parseProbeOutput(string $output): array
    {
        $probe = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        $streams = $probe['streams'] ?? [];
        if (!is_array($streams) || [] === $streams) {
            throw new RuntimeException('FFprobe did not find an image stream.');
        }

        $validStreams = [];
        foreach ($streams as $streamIndex => $stream) {
            if (!is_array($stream)) {
                continue;
            }

            $width = filter_var($stream['width'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $height = filter_var($stream['height'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $frames = filter_var($stream['nb_read_frames'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (false === $frames) {
                $frames = filter_var($stream['nb_frames'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            }

            if (in_array(false, [$width, $height, $frames], true)) {
                continue;
            }

            $validStreams[] = [
                'position' => $streamIndex,
                'stream' => $stream,
                'width' => $width,
                'height' => $height,
                'frames' => $frames,
            ];
        }

        if ([] === $validStreams) {
            throw new RuntimeException('FFprobe returned incomplete animation metadata.');
        }

        $colorStreams = array_values(array_filter($validStreams, fn (array $candidate): bool => !$this->pixelFormatIsMonochrome($candidate['stream']['pix_fmt'] ?? null)));
        $mainStream = $this->selectLongestStream([] !== $colorStreams ? $colorStreams : $validStreams);
        $stream = $mainStream['stream'];
        $width = $mainStream['width'];
        $height = $mainStream['height'];
        $frames = $mainStream['frames'];
        $durationValue = $stream['duration'] ?? $probe['format']['duration'] ?? null;
        $duration = is_numeric($durationValue) && (float) $durationValue > 0 ? (float) $durationValue : null;

        $alphaStream = null;
        foreach ($validStreams as $candidate) {
            if ($candidate['position'] === $mainStream['position']
                || !$this->pixelFormatIsMonochrome($candidate['stream']['pix_fmt'] ?? null)
                || $candidate['width'] !== $width
                || $candidate['height'] !== $height
                || $candidate['frames'] !== $frames) {
                continue;
            }

            $alphaStream = $candidate;
            break;
        }

        $alpha = null !== $alphaStream ? 'stream' : null;
        if (null === $alpha && $this->pixelFormatHasAlpha($stream['pix_fmt'] ?? null)) {
            $alpha = 'packed';
        }

        return [
            'width' => $width,
            'height' => $height,
            'frames' => $frames,
            'duration' => $duration,
            'alpha' => $alpha,
            'stream' => $mainStream['position'],
            'alpha_stream' => $alphaStream['position'] ?? null,
        ];
    }

    protected function pixelFormatIsMonochrome(?string $pixelFormat): bool
    {
        return str_starts_with($pixelFormat ?? '', 'gray');
    }

    private function selectLongestStream(array $streams): array
    {
        return array_reduce($streams, static function (?array $selected, array $candidate): array {
            if (null === $selected || $candidate['frames'] > $selected['frames']) {
                return $candidate;
            }

            return $selected;
        });
    }

    protected function pixelFormatHasAlpha(?string $pixelFormat): bool
    {
        if ('pal8' === $pixelFormat) {
            return true;
        }

        foreach (['abgr', 'argb', 'bgra', 'gbrap', 'rgba', 'ya', 'yuva'] as $prefix) {
            if (str_starts_with($pixelFormat ?? '', $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array{width: int, height: int, frames: int, duration: float|null, alpha: 'packed'|'stream'|null, stream?: int, alpha_stream?: int|null} $metadata
     */
    protected function validateAnimation(array $metadata, array $animationOptions): void
    {
        if ($metadata['frames'] < 2) {
            throw new RuntimeException('The uploaded image is not animated.');
        }

        if (isset($animationOptions['max_frames']) && $metadata['frames'] > $animationOptions['max_frames']) {
            throw new RuntimeException(sprintf('The animation contains %d frames; the configured maximum is %d.', $metadata['frames'], $animationOptions['max_frames']));
        }

        if (isset($animationOptions['max_duration'])) {
            if (null === $metadata['duration']) {
                throw new RuntimeException('FFprobe could not determine the animation duration required by max_duration.');
            }

            if ($metadata['duration'] > $animationOptions['max_duration']) {
                throw new RuntimeException(sprintf('The animation lasts %.3f seconds; the configured maximum is %.3f seconds.', $metadata['duration'], $animationOptions['max_duration']));
            }
        }
    }

    /**
     * @param array{width: int, height: int, frames: int, duration: float|null, alpha: 'packed'|'stream'|null, stream?: int, alpha_stream?: int|null} $metadata
     *
     * @return array{int, int}
     */
    protected function calculateDimensions(array $metadata, array $options): array
    {
        $width = $options['scale_width'] ?? null;
        $height = $options['scale_height'] ?? null;

        if (null !== $width && null === $height) {
            $height = max(1, (int) round($width * $metadata['height'] / $metadata['width']));
        } elseif (null === $width && null !== $height) {
            $width = max(1, (int) round($height * $metadata['width'] / $metadata['height']));
        }

        return [$width ?? $metadata['width'], $height ?? $metadata['height']];
    }

    protected function getTargetFormat(MediaVersionInterface $version): string
    {
        $type = $version->getOptions()['type'] ?? 'keep';

        if ('keep' !== $type) {
            return $type;
        }

        return self::MIME_TYPE_FORMATS[$version->getOriginalVersion()?->getFileMimeType()] ?? '';
    }

    protected function createTemporaryOutputPath(string $extension): string
    {
        return sys_get_temp_dir().'/'.uniqid('sfs_media_animation_', true).'.'.$extension;
    }

    /**
     * @param array{width: int, height: int, frames: int, duration: float|null, alpha: 'packed'|'stream'|null, stream?: int, alpha_stream?: int|null} $metadata
     */
    protected function buildCommand(string $inputPath, string $outputPath, string $targetFormat, int $width, int $height, array $options, array $animationOptions, array $metadata): array
    {
        $filters = [sprintf('scale=%d:%d:flags=lanczos', $width, $height)];
        if (isset($animationOptions['fps'])) {
            $filters[] = 'fps='.$animationOptions['fps'];
        }

        $command = [
            $this->ffmpegBinary,
            '-hide_banner',
            '-loglevel',
            'error',
            '-y',
            '-i',
            $inputPath,
            '-an',
            '-sn',
        ];
        $filter = implode(',', $filters);
        $colorInput = sprintf('0:v:%d', $metadata['stream'] ?? 0);

        if ('avif' === $targetFormat && 'packed' === $metadata['alpha']) {
            $filterGraph = sprintf('[%s]split=2[color_source][alpha_source];[color_source]%s,format=yuv420p,setparams=colorspace=bt709[color];[alpha_source]alphaextract,%s,format=gray,setparams=colorspace=unknown[alpha]', $colorInput, $filter, $filter);
            $command[] = '-filter_complex';
            $command[] = $filterGraph;
            $command[] = '-map';
            $command[] = '[color]';
            $command[] = '-map';
            $command[] = '[alpha]';
        } elseif ('avif' === $targetFormat && 'stream' === $metadata['alpha']) {
            $alphaInput = sprintf('0:v:%d', $metadata['alpha_stream'] ?? 1);
            $filterGraph = sprintf('[%s]%s,format=yuv420p,setparams=colorspace=bt709[color];[%s]%s,format=gray,setparams=colorspace=unknown[alpha]', $colorInput, $filter, $alphaInput, $filter);
            $command[] = '-filter_complex';
            $command[] = $filterGraph;
            $command[] = '-map';
            $command[] = '[color]';
            $command[] = '-map';
            $command[] = '[alpha]';
        } elseif ('stream' === $metadata['alpha']) {
            $alphaInput = sprintf('0:v:%d', $metadata['alpha_stream'] ?? 1);
            $filterGraph = sprintf('[%s]%s[color];[%s]%s[alpha];[color][alpha]alphamerge[output]', $colorInput, $filter, $alphaInput, $filter);
            $command[] = '-filter_complex';
            $command[] = $filterGraph;
            $command[] = '-map';
            $command[] = '[output]';
        } else {
            $command[] = '-map';
            $command[] = $colorInput;
            $command[] = '-filter:v';
            $command[] = $filter;
        }
        $command[] = '-fps_mode';
        $command[] = 'passthrough';
        if (isset($animationOptions['keyframe_interval'])) {
            $command[] = '-g';
            $command[] = (string) $animationOptions['keyframe_interval'];
        }

        $loop = (string) ($animationOptions['loop'] ?? 0);

        switch ($targetFormat) {
            case 'avif':
                $quality = max(0, min(100, $options['avif_quality'] ?? 80));
                $crf = $animationOptions['crf'] ?? (int) round((100 - $quality) * 63 / 100);
                $command[] = '-c:v';
                $command[] = 'libaom-av1';
                $command[] = '-still-picture';
                $command[] = '0';
                $command[] = '-crf';
                $command[] = (string) $crf;
                $command[] = '-b:v';
                $command[] = '0';
                $command[] = '-cpu-used';
                $command[] = (string) ($animationOptions['speed'] ?? 6);
                $command[] = '-loop';
                $command[] = $loop;
                break;

            case 'webp':
                $quality = max(0, min(100, $options['webp_quality'] ?? 80));
                $command[] = '-c:v';
                // Full frames avoid lossy composition errors from libwebp_anim's
                // optimized partial frames, which can leave trails and stripes.
                $command[] = 'libwebp';
                $command[] = '-quality';
                $command[] = (string) $quality;
                $command[] = '-loop';
                $command[] = $loop;
                break;

            case 'apng':
                $command[] = '-c:v';
                $command[] = 'apng';
                $command[] = '-plays';
                $command[] = $loop;
                break;

            case 'gif':
                $command[] = '-c:v';
                $command[] = 'gif';
                $command[] = '-loop';
                $command[] = $loop;
                break;

            default:
                throw new RuntimeException(sprintf('Unsupported animated media target format "%s".', $targetFormat));
        }

        $command[] = $outputPath;

        return $command;
    }

    protected function runFfmpeg(array $command): void
    {
        $process = new Process($command);
        $process->setTimeout($this->ffmpegTimeout);
        $process->mustRun();
    }
}
