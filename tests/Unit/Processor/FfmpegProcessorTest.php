<?php

declare(strict_types=1);

namespace Softspring\MediaBundle\Tests\Unit\Processor;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Softspring\MediaBundle\Entity\MediaVersion;
use Softspring\MediaBundle\Processor\FfmpegProcessor;
use Symfony\Component\HttpFoundation\File\File;

class FfmpegProcessorTest extends TestCase
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        $this->temporaryDirectory = sys_get_temp_dir().'/ffmpeg-processor-test-'.uniqid('', true);
        mkdir($this->temporaryDirectory);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->temporaryDirectory.'/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($this->temporaryDirectory);
    }

    public function testPriority(): void
    {
        $this->assertSame(10, FfmpegProcessor::getPriority());
    }

    public function testSupportsOnlyGeneratedAnimatedImageVersions(): void
    {
        $processor = $this->createProcessor();
        $version = $this->createVersion(['type' => 'avif', 'animated' => true]);

        $this->assertTrue($processor->supports($version));

        $version->setOptions(['type' => 'avif']);
        $this->assertFalse($processor->supports($version));

        $version->setOptions(['type' => 'jpeg', 'animated' => true]);
        $this->assertFalse($processor->supports($version));

        $version->setOptions([
            'type' => 'avif',
            'animated' => true,
            'upload_requirements' => ['mimeTypes' => ['image/avif']],
        ]);
        $this->assertFalse($processor->supports($version));
    }

    public function testKeepsTheAnimatedSourceFormat(): void
    {
        $processor = $this->createProcessor();
        $version = $this->createVersion(['type' => 'keep', 'animated' => true], 'image/gif');

        $this->assertTrue($processor->supports($version));
    }

    public function testProcessesAndScalesAnimatedAvif(): void
    {
        $outputPath = $this->temporaryDirectory.'/result.avif';
        $processor = $this->createProcessor($outputPath);
        $version = $this->createVersion([
            'type' => 'avif',
            'animated' => true,
            'scale_width' => 100,
            'avif_quality' => 80,
            'animation' => [
                'fps' => 25,
                'loop' => 0,
                'crf' => 12,
                'speed' => 7,
                'keyframe_interval' => 25,
                'max_duration' => 3.0,
                'max_frames' => 75,
            ],
        ]);
        $inputPath = $this->temporaryDirectory.'/input.gif';
        file_put_contents($inputPath, 'animated input');
        $version->setUpload(new File($inputPath));

        $processor->process($version);

        $this->assertFalse(file_exists($inputPath));
        $this->assertSame($outputPath, $version->getUpload()?->getRealPath());
        $this->assertSame(100, $version->getWidth());
        $this->assertSame(50, $version->getHeight());
        $this->assertContains('libaom-av1', $processor->command);
        $this->assertContains('scale=100:50:flags=lanczos,fps=25', $processor->command);
        $this->assertCommandOption($processor->command, '-crf', '12');
        $this->assertCommandOption($processor->command, '-cpu-used', '7');
        $this->assertCommandOption($processor->command, '-g', '25');
        $this->assertCommandOption($processor->command, '-loop', '0');
    }

    public function testPreservesPackedAlphaWhenEncodingAvif(): void
    {
        $processor = $this->createProcessor(metadata: [
            'width' => 200,
            'height' => 100,
            'frames' => 50,
            'duration' => 2.0,
            'alpha' => 'packed',
        ]);
        $version = $this->createVersion(['type' => 'avif', 'animated' => true]);
        $inputPath = $this->temporaryDirectory.'/input.apng';
        file_put_contents($inputPath, 'animated input with alpha');
        $version->setUpload(new File($inputPath));

        $processor->process($version);

        $this->assertCommandOption($processor->command, '-map', '[color]');
        $this->assertContains('[alpha]', $processor->command);
        $this->assertContains('[0:v:0]split=2[color_source][alpha_source];[color_source]scale=200:100:flags=lanczos,format=yuv420p[color];[alpha_source]alphaextract,scale=200:100:flags=lanczos,format=gray[alpha]', $processor->command);
    }

    public function testEncodesWebpAsIndependentFullFrames(): void
    {
        $processor = $this->createProcessor($this->temporaryDirectory.'/result.webp');
        $version = $this->createVersion([
            'type' => 'webp',
            'animated' => true,
            'webp_quality' => 85,
        ]);
        $inputPath = $this->temporaryDirectory.'/input.avif';
        file_put_contents($inputPath, 'animated input');
        $version->setUpload(new File($inputPath));

        $processor->process($version);

        $this->assertCommandOption($processor->command, '-c:v', 'libwebp');
        $this->assertNotContains('libwebp_anim', $processor->command);
        $this->assertCommandOption($processor->command, '-quality', '85');
    }

    public function testRejectsStaticImages(): void
    {
        $processor = $this->createProcessor(metadata: [
            'width' => 200,
            'height' => 100,
            'frames' => 1,
            'duration' => null,
            'alpha' => null,
        ]);
        $version = $this->createVersion(['type' => 'webp', 'animated' => true]);
        $inputPath = $this->temporaryDirectory.'/input.webp';
        file_put_contents($inputPath, 'static input');
        $version->setUpload(new File($inputPath));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The uploaded image is not animated.');

        $processor->process($version);
    }

    public function testRejectsAnimationOverConfiguredFrameLimit(): void
    {
        $processor = $this->createProcessor();
        $version = $this->createVersion([
            'type' => 'webp',
            'animated' => true,
            'animation' => ['max_frames' => 25],
        ]);
        $inputPath = $this->temporaryDirectory.'/input.webp';
        file_put_contents($inputPath, 'animated input');
        $version->setUpload(new File($inputPath));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The animation contains 50 frames; the configured maximum is 25.');

        $processor->process($version);
    }

    public function testParsesProbeMetadata(): void
    {
        $processor = $this->createProcessor();
        $metadata = $processor->parseProbeJson(json_encode([
            'streams' => [[
                'width' => 400,
                'height' => 204,
                'nb_frames' => '49',
                'nb_read_frames' => '50',
                'pix_fmt' => 'yuva420p',
            ]],
            'format' => ['duration' => '2.000000'],
        ], JSON_THROW_ON_ERROR));

        $this->assertSame([
            'width' => 400,
            'height' => 204,
            'frames' => 50,
            'duration' => 2.0,
            'alpha' => 'packed',
        ], $metadata);
    }

    private function createProcessor(?string $outputPath = null, ?array $metadata = null): TestingFfmpegProcessor
    {
        return new TestingFfmpegProcessor(
            '/usr/local/bin/ffmpeg',
            '/usr/local/bin/ffprobe',
            12,
            $outputPath ?? $this->temporaryDirectory.'/result.avif',
            $metadata ?? [
                'width' => 200,
                'height' => 100,
                'frames' => 50,
                'duration' => 2.0,
                'alpha' => null,
            ],
        );
    }

    private function createVersion(array $options, string $mimeType = 'image/gif'): MediaVersion
    {
        $originalVersion = new MediaVersion('_original');
        $originalVersion->setFileMimeType($mimeType);

        $version = new MediaVersion('small');
        $version->setOriginalVersion($originalVersion);
        $version->setOptions($options);

        return $version;
    }

    private function assertCommandOption(array $command, string $option, string $expectedValue): void
    {
        $position = array_search($option, $command, true);

        $this->assertNotFalse($position, sprintf('Command does not contain option %s.', $option));
        $this->assertSame($expectedValue, $command[$position + 1]);
    }
}

class TestingFfmpegProcessor extends FfmpegProcessor
{
    public array $command = [];

    public function __construct(
        string $ffmpegBinary,
        string $ffprobeBinary,
        int $ffmpegTimeout,
        private readonly string $outputPath,
        private readonly array $metadata,
    ) {
        parent::__construct($ffmpegBinary, $ffprobeBinary, $ffmpegTimeout);
    }

    public function parseProbeJson(string $output): array
    {
        return $this->parseProbeOutput($output);
    }

    protected function probeAnimation(string $path): array
    {
        return $this->metadata;
    }

    protected function createTemporaryOutputPath(string $extension): string
    {
        return $this->outputPath;
    }

    protected function runFfmpeg(array $command): void
    {
        $this->command = $command;
        file_put_contents($this->outputPath, 'encoded animation');
    }
}
