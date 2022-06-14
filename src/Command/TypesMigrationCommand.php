<?php

namespace Softspring\MediaBundle\Command;

use Softspring\MediaBundle\Manager\MediaManagerInterface;
use Softspring\MediaBundle\Manager\MediaTypeManagerInterface;
use Softspring\MediaBundle\Manager\MediaVersionManagerInterface;
use Softspring\MediaBundle\Model\MediaInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TypesMigrationCommand extends Command
{
    protected static $defaultName = 'sfs:media:types-migration';

    protected MediaManagerInterface $mediaManager;
    protected MediaVersionManagerInterface $mediaVersionManager;
    protected MediaTypeManagerInterface $typesManager;

    public function __construct(MediaManagerInterface $mediaManager, MediaVersionManagerInterface $mediaVersionManager, MediaTypeManagerInterface $typesManager)
    {
        parent::__construct();
        $this->mediaManager = $mediaManager;
        $this->mediaVersionManager = $mediaVersionManager;
        $this->typesManager = $typesManager;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $medias = $this->mediaManager->getRepository()->findAll();

        /** @var MediaInterface $media */
        foreach ($medias as $media) {
            $typeConfig = $this->typesManager->getType($media->getType());

            if (!$typeConfig) {
                $output->writeln(sprintf('<error>Media "%s" has an error. Type "%s" has been deleted</error>', $media->getName(), $media->getType()));
                continue;
            }

            $output->writeln(sprintf('Media "%s" of type "%s"', $media->getName(), $media->getType()));

            $checkVersions = $media->checkVersions($typeConfig);

            foreach ($checkVersions['ok'] as $versionId) if ($versionId !== '_original') {
                $output->writeln(sprintf(' - version "%s" is <fg=green>OK</>', $versionId));
            }

            foreach ($checkVersions['new'] as $versionId) {
                $output->write(sprintf(' - version "%s" is new in config, needs to be created: ', $versionId));
                try {
                    $this->mediaManager->generateVersion($media, $versionId);
                    $output->writeln('<fg=green>CREATED</>');
                } catch (\Exception $e) {
                    $output->writeln('<error>ERROR</error>');
                }
            }

            foreach ($checkVersions['changed'] as $versionId => $changes) {
                $changedOptionsString = implode(', ', array_map(fn ($v) => $v['string'], $changes));
                $output->write(sprintf(' - version "%s" needs to be recreated (%s): ', $versionId, $changedOptionsString));
                try {
                    $this->mediaManager->generateVersion($media, $versionId);
                    $output->writeln('<fg=green>RECREATED</>');
                } catch (\Exception $e) {
                    $output->writeln('<error>ERROR</error>');
                }
            }

            foreach ($checkVersions['delete'] as $versionId) {
                $output->write(sprintf(' - version "%s" to be deleted from database (has been deleted from config) ', $versionId));
                try {
                    $this->mediaManager->deleteVersion($media->getVersion($versionId));
                    $output->writeln('<fg=green>DELETED</>');
                } catch (\Exception $e) {
                    $output->writeln('<error>ERROR</error>');
                }
            }

            $output->writeln('');
        }

        return Command::SUCCESS;
    }
}
