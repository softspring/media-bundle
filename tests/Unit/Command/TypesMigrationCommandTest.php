<?php

declare(strict_types=1);

namespace Softspring\MediaBundle\Tests\Unit\Command;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Softspring\MediaBundle\Command\TypesMigrationCommand;
use Softspring\MediaBundle\Entity\Media;
use Softspring\MediaBundle\EntityManager\MediaManagerInterface;
use Softspring\MediaBundle\Type\MediaTypesCollection;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Process\Process;

class TypesMigrationCommandTest extends TestCase
{
    private function command(array $ids, array &$visited, bool $fail = false, bool $force = false): TestingTypesMigrationCommand
    {
        $media = new Media();
        $media->setName('Test');
        $media->setType('content');
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('find')->willReturnCallback(function ($id) use (&$visited, $media): Media {
            $visited[] = (string) $id;

            return $media;
        });
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('isOpen')->willReturn(true);
        $manager = $this->createMock(MediaManagerInterface::class);
        $manager->method('getRepository')->willReturn($repository);
        $manager->method('getEntityManager')->willReturn($em);
        if ($fail) {
            $manager->method('migrate')->willThrowException(new RuntimeException('Failed encoding'));
        } elseif ($force) {
            $manager->expects($this->once())->method('migrate')->with($media, $this->anything(), true);
        } else {
            $manager->expects($this->never())->method('migrate');
        }
        $types = $this->createMock(MediaTypesCollection::class);
        $types->method('getType')->willReturn(['versions' => []]);
        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn(sys_get_temp_dir());
        $kernel->method('getEnvironment')->willReturn('test');
        $command = new TestingTypesMigrationCommand($manager, $types, $kernel);
        $command->ids = $ids;

        return $command;
    }

    public function testShardsCoverEveryIdExactlyOnceInDryRun(): void
    {
        $ids = array_map('strval', range(1, 100));
        $visited = [];
        for ($index = 0; $index < 4; ++$index) {
            $tester = new CommandTester($this->command($ids, $visited));
            self::assertSame(0, $tester->execute(['--worker-index' => $index, '--worker-count' => 4, '--dry-run' => true]));
        }
        self::assertCount(100, $visited);
        sort($ids);
        sort($visited);
        self::assertSame($ids, $visited);
    }

    public function testMigrationFailureReturnsFailureAndContinues(): void
    {
        $visited = [];
        $tester = new CommandTester($this->command(['1', '2'], $visited, true));
        self::assertSame(1, $tester->execute([]));
        self::assertSame(['1', '2'], $visited);
        self::assertStringContainsString('2 failed', $tester->getDisplay());
        self::assertStringContainsString('Failed encoding', $tester->getDisplay());
    }

    public function testParentReportsFailedWorkerAndPropagatesOptions(): void
    {
        $visited = [];
        $command = $this->command([], $visited);
        $command->workerExitCode = 1;
        $tester = new CommandTester($command);
        self::assertSame(1, $tester->execute(['--workers' => 2, '--dry-run' => true, '--force' => true, '--type' => 'content', '--batch-size' => 7]));
        self::assertCount(2, $command->workerArguments);
        foreach ($command->workerArguments as $arguments) {
            self::assertContains('--dry-run', $arguments);
            self::assertContains('--force', $arguments);
            self::assertContains('--type=content', $arguments);
            self::assertContains('--batch-size=7', $arguments);
            self::assertContains('--worker-count=2', $arguments);
        }
        self::assertStringContainsString('2 failed', $tester->getDisplay());
    }

    public function testInvalidWorkerIndexIsRejected(): void
    {
        $visited = [];
        $this->expectException(InvalidArgumentException::class);
        (new CommandTester($this->command([], $visited)))->execute(['--worker-index' => 2, '--worker-count' => 2]);
    }

    public function testSuccessfulWorkersReturnSuccess(): void
    {
        $visited = [];
        $tester = new CommandTester($this->command([], $visited));
        self::assertSame(0, $tester->execute(['--workers' => 2, '--dry-run' => true]));
        self::assertStringContainsString('2 successful, 0 failed', $tester->getDisplay());
    }

    public function testForceIsPassedToMediaManager(): void
    {
        $visited = [];
        $tester = new CommandTester($this->command(['1'], $visited, false, true));
        self::assertSame(0, $tester->execute(['--force' => true]));
    }

    public function testForcedDryRunDoesNotCallMigration(): void
    {
        $visited = [];
        $tester = new CommandTester($this->command(['1'], $visited));
        self::assertSame(0, $tester->execute(['--force' => true, '--dry-run' => true]));
    }
}

class TestingTypesMigrationCommand extends TypesMigrationCommand
{
    public array $ids = [];
    public array $workerArguments = [];
    public int $workerExitCode = 0;

    protected function mediaIds(int $batchSize, ?string $type): iterable
    {
        return $this->ids;
    }

    protected function createWorkerProcess(array $arguments): Process
    {
        $this->workerArguments[] = $arguments;

        return new Process([PHP_BINARY, '-r', sprintf('exit(%d);', $this->workerExitCode)], null, null, null, null);
    }
}
