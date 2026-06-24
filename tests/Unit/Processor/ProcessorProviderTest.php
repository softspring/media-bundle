<?php

declare(strict_types=1);

namespace Softspring\MediaBundle\Tests\Unit\Processor;

use ArrayIterator;
use PHPUnit\Framework\TestCase;
use Softspring\MediaBundle\Entity\MediaVersion;
use Softspring\MediaBundle\Model\MediaVersionInterface;
use Softspring\MediaBundle\Processor\ProcessorInterface;
use Softspring\MediaBundle\Processor\ProcessorProvider;

class ProcessorProviderTest extends TestCase
{
    public function testReturnsOnlySupportedProcessors(): void
    {
        $supportedProcessor = new TestingProcessor(true);
        $unsupportedProcessor = new TestingProcessor(false);
        $version = new MediaVersion();

        $provider = new ProcessorProvider(new ArrayIterator([$supportedProcessor, $unsupportedProcessor]));

        $this->assertSame([$supportedProcessor], $provider->getProcessors($version));
    }

    public function testAppliesOnlySupportedProcessors(): void
    {
        $supportedProcessor = new TestingProcessor(true);
        $unsupportedProcessor = new TestingProcessor(false);
        $version = new MediaVersion();

        $provider = new ProcessorProvider([$supportedProcessor, $unsupportedProcessor]);
        $provider->applyProcessors($version);

        $this->assertSame(1, $supportedProcessor->processed);
        $this->assertSame(0, $unsupportedProcessor->processed);
    }
}

class TestingProcessor implements ProcessorInterface
{
    public int $processed = 0;

    public function __construct(private readonly bool $supported)
    {
    }

    public function supports(MediaVersionInterface $version): bool
    {
        return $this->supported;
    }

    public function process(MediaVersionInterface $version): void
    {
        ++$this->processed;
    }

    public static function getPriority(): int
    {
        return 0;
    }
}
