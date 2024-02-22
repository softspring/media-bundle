<?php

namespace Softspring\MediaBundle\Command;

use Softspring\MediaBundle\EntityManager\MediaManagerInterface;
use Softspring\MediaBundle\Exception\InvalidTypeException;
use Softspring\MediaBundle\Model\MediaInterface;
use Softspring\MediaBundle\Type\MediaTypesCollection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

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

    /**
     * @throws InvalidTypeException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $medias = $this->mediaManager->getRepository()->findAll();

        /** @var MediaInterface $media */
        foreach ($medias as $media) {
            $typeConfig = $this->mediaTypesCollection->getType($media->getType());

            if (!$typeConfig) {
                $output->writeln(sprintf('<error>Media "%s" has an error. Type "%s" has been deleted</error>', $media->getName(), $media->getType()));
                continue;
            }

            $output->writeln(sprintf('Media "%s" of type "%s"', $media->getName(), $media->getType()));

            $this->mediaManager->migrate($media, $output);

            $output->writeln('');
        }

        return Command::SUCCESS;
    }
}
