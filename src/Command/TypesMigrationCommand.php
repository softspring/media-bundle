<?php

namespace Softspring\MediaBundle\Command;

use Softspring\MediaBundle\EntityManager\MediaManagerInterface;
use Softspring\MediaBundle\Exception\InvalidTypeException;
use Softspring\MediaBundle\Model\MediaInterface;
use Softspring\MediaBundle\Type\MediaTypesCollection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class TypesMigrationCommand extends Command
{
    public function __construct(protected MediaManagerInterface $mediaManager, protected MediaTypesCollection $mediaTypesCollection)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('sfs:media:types-migration');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $medias = $this->mediaManager->getRepository()->findAll();

        $this->mediaManager->setMigrating(true);

        /** @var MediaInterface $media */
        foreach ($medias as $media) {
            try {
                $typeConfig = $this->mediaTypesCollection->getType($media->getType());

                if (!$typeConfig) {
                    $output->writeln(sprintf('<error>Media "%s" has an error. Type "%s" has been deleted</error>', $media->getName(), $media->getType()));
                    continue;
                }

                $output->writeln(sprintf('Media "%s" of type "%s"', $media->getName(), $media->getType()));

                $this->mediaManager->migrate($media, $output);

                $output->writeln('');
            } catch (InvalidTypeException $e) {
                $output->writeln(sprintf('<error>Media "%s" has an error. Type "%s" is invalid</error>', $media->getName(), $media->getType()));
            } catch (Throwable $e) {
                $output->writeln(sprintf(
                    '<error>Media "%s" of type "%s" could not be migrated: %s</error>',
                    $media->getName(),
                    $media->getType(),
                    $e->getMessage(),
                ));
            }
        }

        return Command::SUCCESS;
    }
}
