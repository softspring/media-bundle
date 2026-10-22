<?php

namespace Softspring\MediaBundle\Command;

use InvalidArgumentException;
use Softspring\MediaBundle\EntityManager\MediaManagerInterface;
use Softspring\MediaBundle\Exception\InvalidTypeException;
use Softspring\MediaBundle\Helper\TypeChecker;
use Softspring\MediaBundle\Model\MediaInterface;
use Softspring\MediaBundle\Type\MediaTypesCollection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Process\Process;
use Throwable;

#[AsCommand(name: 'sfs:media:types-migration', description: 'Migrate media versions, optionally using independent worker processes')]
class TypesMigrationCommand extends Command
{
    public function __construct(protected MediaManagerInterface $mediaManager, protected MediaTypesCollection $mediaTypesCollection, protected KernelInterface $kernel)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('workers', null, InputOption::VALUE_REQUIRED, 'Number of local worker processes', '1')
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Number of IDs fetched per database page', '100')
            ->addOption('worker-index', null, InputOption::VALUE_REQUIRED, 'Zero-based shard index (for distributed workers)', '0')
            ->addOption('worker-count', null, InputOption::VALUE_REQUIRED, 'Total number of distributed shards', '1')
            ->addOption('type', null, InputOption::VALUE_REQUIRED, 'Only migrate this media type')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report planned changes without writing media or files');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $workers = $this->integerOption($input, 'workers', 1);
        $this->integerOption($input, 'batch-size', 1);
        $count = $this->integerOption($input, 'worker-count', 1);
        $index = $this->integerOption($input, 'worker-index', 0);
        if ($index >= $count || ($workers > 1 && ($count > 1 || $index > 0))) {
            throw new InvalidArgumentException('Worker index must be below worker count; do not combine local workers with distributed shards.');
        }

        return $workers > 1 ? $this->runWorkers($input, $output, $workers) : $this->runShard($input, $output, $index, $count);
    }

    private function integerOption(InputInterface $input, string $name, int $minimum): int
    {
        $value = filter_var($input->getOption($name), FILTER_VALIDATE_INT);
        if (false === $value || $value < $minimum) {
            throw new InvalidArgumentException(sprintf('--%s must be an integer >= %d.', $name, $minimum));
        }

        return $value;
    }

    protected function createWorkerProcess(array $arguments): Process
    {
        return new Process($arguments, $this->kernel->getProjectDir(), null, null, null);
    }

    private function runWorkers(InputInterface $input, OutputInterface $output, int $count): int
    {
        $started = microtime(true);
        $processes = [];
        $failed = 0;
        $output->writeln(sprintf('Starting %d media migration workers.', $count));
        try {
            for ($index = 0; $index < $count; ++$index) {
                $arguments = [PHP_BINARY, $this->kernel->getProjectDir().'/bin/console', 'sfs:media:types-migration', '--no-interaction', '--no-ansi', '--env='.$this->kernel->getEnvironment(), '--worker-index='.$index, '--worker-count='.$count, '--batch-size='.$input->getOption('batch-size')];
                if (!$this->kernel->isDebug()) {
                    $arguments[] = '--no-debug';
                }
                if ($input->getOption('type')) {
                    $arguments[] = '--type='.$input->getOption('type');
                }
                if ($input->getOption('dry-run')) {
                    $arguments[] = '--dry-run';
                }
                $processes[$index] = $process = $this->createWorkerProcess($arguments);
                $process->start();
            }
            while ($processes) {
                foreach ($processes as $index => $process) {
                    $running = $process->isRunning();
                    foreach ([$process->getIncrementalOutput(), $process->getIncrementalErrorOutput()] as $buffer) {
                        if ('' !== $buffer) {
                            $output->write(sprintf('[worker %d] ', $index).$buffer, false, OutputInterface::OUTPUT_RAW);
                        }
                    }
                    if (!$running) {
                        if (!$process->isSuccessful()) {
                            ++$failed;
                            $output->writeln(sprintf('<error>Worker %d failed (exit %s).</error>', $index, $process->getExitCode()));
                        }
                        unset($processes[$index]);
                    }
                }
                if ([] !== $processes) {
                    usleep(100000);
                }
            }
        } finally {
            foreach ($processes as $process) {
                $process->stop();
            }
        }
        $output->writeln(sprintf('Workers finished: %d successful, %d failed; %.1fs elapsed.', $count - $failed, $failed, microtime(true) - $started));

        return 0 !== $failed ? Command::FAILURE : Command::SUCCESS;
    }

    /** Keyset pagination keeps memory bounded and remains stable as versions change. */
    protected function mediaIds(int $batchSize, ?string $type): iterable
    {
        $repository = $this->mediaManager->getRepository();
        $em = $this->mediaManager->getEntityManager();
        $idType = $em->getClassMetadata($repository->getClassName())->getTypeOfField('id');
        $lastId = null;
        do {
            $qb = $repository->createQueryBuilder('m')->select('m.id AS id')->orderBy('m.id', 'ASC')->setMaxResults($batchSize);
            if (null !== $lastId) {
                $qb->andWhere('m.id > :lastId')->setParameter('lastId', $lastId, $idType);
            }
            if (null !== $type) {
                $qb->andWhere('m.type = :type')->setParameter('type', $type);
            }
            $rows = $qb->getQuery()->getScalarResult();
            foreach ($rows as $row) {
                $lastId = $row['id'];
                yield $lastId;
            }
        } while (count($rows) === $batchSize);
    }

    private function runShard(InputInterface $input, OutputInterface $output, int $index, int $count): int
    {
        $started = microtime(true);
        $processed = $failed = 0;
        $em = $this->mediaManager->getEntityManager();
        $wasMigrating = $this->mediaManager->isMigrating();

        $this->mediaManager->setMigrating(true);

        try {
            foreach ($this->mediaIds((int) $input->getOption('batch-size'), $input->getOption('type')) as $id) {
                if (hexdec(substr(hash('sha256', (string) $id), 0, 7)) % $count !== $index) {
                    continue;
                }
                $media = null;
                $mediaStarted = microtime(true);
                try {
                    $media = $this->mediaManager->getRepository()->find($id);
                    if (!$media instanceof MediaInterface) {
                        continue;
                    }
                    ++$processed;
                    $typeConfig = $this->mediaTypesCollection->getType($media->getType());

                    if ([] === $typeConfig) {
                        $output->writeln(sprintf('<error>Media "%s" has an error. Type "%s" has been deleted</error>', $media->getName(), $media->getType()));
                        ++$failed;
                        continue;
                    }

                    $output->writeln(sprintf('Media "%s" of type "%s"', $media->getName(), $media->getType()));

                    if ($input->getOption('dry-run')) {
                        $changes = TypeChecker::checkMedia($media, $typeConfig);
                        $output->writeln(sprintf('Dry run: %d new, %d changed, %d deleted versions.', count($changes['new']), count($changes['changed']), count($changes['delete'])));
                    } else {
                        $this->mediaManager->migrate($media, $output);
                    }

                    $output->writeln('');
                } catch (InvalidTypeException $e) {
                    ++$failed;
                    $output->writeln(sprintf('<error>Media "%s" has an error. Type "%s" is invalid</error>', $media->getName(), $media->getType()));
                } catch (Throwable $e) {
                    ++$failed;
                    $output->writeln(sprintf(
                        '<error>Media "%s" of type "%s" could not be migrated: %s</error>',
                        $media?->getName() ?? (string) $id,
                        $media?->getType() ?? 'unknown',
                        $e->getMessage(),
                    ));
                } finally {
                    $output->writeln(sprintf('Media %s finished in %.2fs; processed=%d, failed=%d.', (string) $id, microtime(true) - $mediaStarted, $processed, $failed));
                    $em->clear();
                }
                if (!$em->isOpen()) {
                    $output->writeln('<error>Entity manager is closed; aborting this worker.</error>');
                    break;
                }
            }
        } finally {
            $this->mediaManager->setMigrating($wasMigrating);
        }
        $output->writeln(sprintf('Shard %d/%d: %d processed, %d failed; %.1fs elapsed%s.', $index, $count, $processed, $failed, microtime(true) - $started, $input->getOption('dry-run') ? ' (dry run)' : ''));

        return 0 !== $failed ? Command::FAILURE : Command::SUCCESS;
    }
}
